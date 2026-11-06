<?php

namespace Tests\Feature;

use App\Contracts\Translator;
use App\Filament\Resources\Pages\Pages\EditPage;
use App\Models\Language;
use App\Models\Page;
use App\Models\PageTranslation;
use App\Models\User;
use App\Support\Translation\GeminiTranslator;
use App\Support\Translation\PageTranslator;
use App\Support\Translation\TranslationFailed;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Machine translation: what travels, what must not, and what an editor sees.
 *
 * The network is faked throughout, so these tests describe the contract with
 * the service rather than depending on it being reachable.
 */
class TranslationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Language::query()->create(['code' => 'en', 'name' => 'English', 'is_default' => true, 'sort_order' => 1]);
        Language::query()->create(['code' => 'my', 'name' => 'Myanmar', 'sort_order' => 2]);
        Language::forgetCache();

        config([
            'ai.gemini.key' => 'test-key',
            'ai.gemini.model' => 'gemini-test',
            'ai.batch' => 40,
        ]);
    }

    /** A translator that answers deterministically, without the network. */
    private function fakeTranslator(): Translator
    {
        return new class implements Translator
        {
            public function translate(array $texts, string $from, string $to): array
            {
                return array_map(fn (string $text): string => "[{$to}] {$text}", $texts);
            }

            public function isConfigured(): bool
            {
                return true;
            }
        };
    }

    /**
     * @return array<string, mixed>
     */
    private function sourceState(): array
    {
        return [
            'title' => 'About us',
            'excerpt' => 'Who we are',
            'seo' => ['title' => 'About Ruma', 'description' => 'Organic since 2015'],
            'content_blocks' => [
                ['type' => 'section', 'data' => [
                    'layout' => 'split',
                    'fields' => [
                        ['type' => 'title', 'value' => 'Our story'],
                        ['type' => 'image', 'value' => 'https://example.com/field.jpg'],
                        ['type' => 'checklist', 'items' => [
                            ['text' => 'Organic'],
                            ['text' => 'Grown locally'],
                        ]],
                        ['type' => 'button', 'value' => 'Read more', 'url' => '/about'],
                    ],
                ]],
            ],
        ];
    }

    public function test_only_words_are_translated_and_structure_is_copied(): void
    {
        $result = (new PageTranslator($this->fakeTranslator()))
            ->translatePage($this->sourceState(), [], 'en', 'my');

        $state = $result['state'];

        $this->assertSame('[my] About us', $state['title']);
        $this->assertSame('[my] Who we are', $state['excerpt']);
        $this->assertSame('[my] About Ruma', $state['seo']['title']);

        $block = $state['content_blocks'][0];

        $this->assertSame('section', $block['type']);
        $this->assertSame('split', $block['data']['layout']);
        $this->assertSame('[my] Our story', $block['data']['fields'][0]['value']);

        // An image pointer is an address, not a sentence.
        $this->assertSame('https://example.com/field.jpg', $block['data']['fields'][1]['value']);

        $this->assertSame('[my] Organic', $block['data']['fields'][2]['items'][0]['text']);
        $this->assertGreaterThan(0, $result['translated']);
    }

    public function test_wording_an_editor_already_wrote_is_kept_unless_asked_to_overwrite(): void
    {
        $target = ['title' => 'ကျွန်ုပ်တို့အကြောင်း'];

        $kept = (new PageTranslator($this->fakeTranslator()))
            ->translatePage($this->sourceState(), $target, 'en', 'my');

        $this->assertSame('ကျွန်ုပ်တို့အကြောင်း', $kept['state']['title']);

        $replaced = (new PageTranslator($this->fakeTranslator()))
            ->translatePage($this->sourceState(), $target, 'en', 'my', overwrite: true);

        $this->assertSame('[my] About us', $replaced['state']['title']);
    }

    public function test_an_overwrite_refits_sections_whose_fields_drifted(): void
    {
        // The products-page case: a button field was added in English after
        // the other language was translated. The shapes no longer match, and
        // the run used to skip the whole page silently. Now the destination is
        // refitted to the source structure and every field is re-translated.
        $target = ['content_blocks' => [
            ['type' => 'section', 'data' => [
                'layout' => 'split',
                'fields' => [
                    ['type' => 'title', 'value' => 'Our story'],
                    ['type' => 'image', 'value' => 'https://example.com/field.jpg'],
                ],
            ]],
        ]];

        $result = (new PageTranslator($this->fakeTranslator()))
            ->translatePage($this->sourceState(), $target, 'en', 'my', overwrite: true);

        $fields = array_values($result['state']['content_blocks'][0]['data']['fields']);

        $this->assertCount(4, $fields, 'The field the destination lacks arrives from the source.');
        $this->assertSame('[my] Our story', $fields[0]['value']);
        $this->assertSame('https://example.com/field.jpg', $fields[1]['value']);
        $this->assertSame('[my] Organic', $fields[2]['items'][0]['text']);

        // The button's label is prose and is translated; its url is not.
        $this->assertSame('[my] Read more', $fields[3]['value']);
        $this->assertSame('/about', $fields[3]['url']);
    }

    public function test_an_overwrite_carries_a_changed_image_into_the_other_language(): void
    {
        // The editor replaced the photo in English; the other language must
        // show the new one without a second upload, keeping its own words.
        $target = ['content_blocks' => [
            ['type' => 'section', 'data' => [
                'layout' => 'split',
                'fields' => [
                    ['type' => 'title', 'value' => '[my] Our story'],
                    ['type' => 'image', 'value' => 'https://example.com/old.jpg', 'alt' => 'Old alt'],
                ],
            ]],
        ]];

        $result = (new PageTranslator($this->fakeTranslator()))
            ->translatePage($this->sourceState(), $target, 'en', 'my', overwrite: true);

        $fields = array_values($result['state']['content_blocks'][0]['data']['fields']);

        $this->assertSame('https://example.com/field.jpg', $fields[1]['value']);
        $this->assertSame('Old alt', $fields[1]['alt'], 'The caption the locale wrote is kept.');
        $this->assertSame('[my] Our story', $fields[0]['value']);
    }

    public function test_a_section_of_another_kind_is_replaced_by_the_source_structure(): void
    {
        // Structure belongs to the source language: a destination section that
        // matches nothing in the source gives way to the source's block, in
        // the language it was written in, ready to be translated.
        $target = ['content_blocks' => [
            ['type' => 'contact_form', 'data' => ['title' => 'Write to us']],
        ]];

        $result = (new PageTranslator($this->fakeTranslator()))
            ->translatePage($this->sourceState(), $target, 'en', 'my', overwrite: true);

        $this->assertSame(
            'section',
            $result['state']['content_blocks'][0]['type'],
        );
        $this->assertSame(
            '[my] Our story',
            $result['state']['content_blocks'][0]['data']['fields'][0]['value'],
        );
    }

    public function test_without_an_overwrite_drifted_sections_are_left_alone(): void
    {
        $target = ['content_blocks' => [
            ['type' => 'contact_form', 'data' => ['title' => 'Write to us']],
        ]];

        $result = (new PageTranslator($this->fakeTranslator()))
            ->translatePage($this->sourceState(), $target, 'en', 'my');

        $this->assertSame(
            'contact_form',
            $result['state']['content_blocks'][0]['type'],
        );
        $this->assertSame(
            'Write to us',
            $result['state']['content_blocks'][0]['data']['title'],
        );
    }

    public function test_the_service_receives_one_batch_and_answers_in_order(): void
    {
        Http::preventStrayRequests();
        Http::fake(function (Request $request) {
            $prompt = (string) $request->data()['contents'][0]['parts'][0]['text'];
            preg_match('/Values \(JSON array of \d+ strings\):\s*(\[.*\])$/s', $prompt, $matches);

            $values = json_decode($matches[1] ?? '[]', true);

            return Http::response([
                'candidates' => [[
                    'content' => ['parts' => [['text' => json_encode([
                        'translations' => array_map(fn (string $value): string => 'TR:'.$value, $values),
                    ])]]],
                ]],
            ]);
        });

        $translated = (new GeminiTranslator)->translate(['Hello', 'Good soil'], 'en', 'my');

        $this->assertSame(['TR:Hello', 'TR:Good soil'], $translated);

        Http::assertSentCount(1);
        Http::assertSent(fn (Request $request): bool => $request->hasHeader('x-goog-api-key', 'test-key')
            && str_contains($request->url(), 'gemini-test:generateContent'));
    }

    public function test_a_long_page_is_split_into_batches(): void
    {
        config(['ai.batch' => 1]);

        Http::preventStrayRequests();
        Http::fake(function (Request $request) {
            $prompt = (string) $request->data()['contents'][0]['parts'][0]['text'];
            preg_match('/Values \(JSON array of \d+ strings\):\s*(\[.*\])$/s', $prompt, $matches);

            $values = json_decode($matches[1] ?? '[]', true);

            return Http::response([
                'candidates' => [[
                    'content' => ['parts' => [['text' => json_encode(['translations' => $values])]]],
                ]],
            ]);
        });

        $translated = (new GeminiTranslator)->translate(['a', 'b', 'c'], 'en', 'my');

        $this->assertSame(['a', 'b', 'c'], $translated);
        Http::assertSentCount(3);
    }

    public function test_without_a_key_the_editor_is_told_what_is_missing(): void
    {
        config(['ai.gemini.key' => '']);

        $this->expectException(TranslationFailed::class);
        $this->expectExceptionMessage('GEMINI_API_KEY');

        (new GeminiTranslator)->translate(['Hello'], 'en', 'my');
    }

    public function test_an_answer_that_does_not_match_the_input_is_refused(): void
    {
        Http::preventStrayRequests();
        Http::fake([
            '*' => Http::response([
                'candidates' => [[
                    'content' => ['parts' => [['text' => '{"translations": ["only one"]}']]],
                ]],
            ]),
        ]);

        $this->expectException(TranslationFailed::class);

        (new GeminiTranslator)->translate(['one', 'two'], 'en', 'my');
    }

    public function test_the_translate_action_queues_a_job_that_writes_the_other_languages(): void
    {
        $page = Page::query()->create([
            'kind' => Page::KIND_PAGE,
            'slug' => 'about',
            'status' => Page::STATUS_PUBLISHED,
            'published_at' => now(),
        ]);

        PageTranslation::query()->create([
            'page_id' => $page->getKey(),
            'locale' => 'en',
            'title' => 'About us',
            'excerpt' => 'Who we are',
            'content_blocks' => $this->sourceState()['content_blocks'],
        ]);

        $this->app->instance(Translator::class, $this->fakeTranslator());

        $editor = User::factory()->admin()->create();

        // The queue runs synchronously in tests, so the dispatched job lands
        // here: it reads the saved source, writes the target row and notifies.
        Livewire::actingAs($editor)
            ->test(EditPage::class, ['record' => $page->getRouteKey()])
            ->callAction('translate', [
                'from' => 'en',
                'to' => ['my'],
                'overwrite' => false,
            ])
            ->assertHasNoActionErrors()
            ->assertNotified();

        $my = PageTranslation::query()
            ->where('page_id', $page->getKey())
            ->where('locale', 'my')
            ->first();

        $this->assertNotNull($my, 'The job should write the target language row.');
        $this->assertSame('[my] About us', $my->title);
        $this->assertSame('[my] Who we are', $my->excerpt);

        $block = $my->blocks()[0] ?? [];
        $title = null;

        foreach ($block['data']['fields'] ?? [] as $field) {
            if (($field['type'] ?? null) === 'title') {
                $title = $field['value'] ?? null;
            }
        }

        $this->assertSame('section', $block['type'] ?? null);
        $this->assertSame('split', $block['data']['layout'] ?? null);
        $this->assertSame('[my] Our story', $title);

        // The source language is left exactly as the editor saved it.
        $this->assertSame(
            'About us',
            PageTranslation::query()->where('page_id', $page->getKey())->where('locale', 'en')->value('title'),
        );

        // The editor is told the work finished, not just that it was queued.
        $this->assertDatabaseHas('notifications', [
            'notifiable_id' => $editor->getKey(),
            'notifiable_type' => User::class,
        ]);
        $this->assertStringContainsString('strings translated', $editor->refresh()->notifications->first()?->data['title'] ?? '');
    }

    public function test_translating_again_refreshes_wording_the_other_language_already_had(): void
    {
        // The Contact-page case: English was edited, but the other language
        // still holds the old wording. One Translate (default settings) must
        // bring it up to date — not leave it for a manual second edit.
        $page = Page::query()->create([
            'kind' => Page::KIND_PAGE,
            'slug' => 'contact',
            'status' => Page::STATUS_PUBLISHED,
            'published_at' => now(),
        ]);

        PageTranslation::query()->create([
            'page_id' => $page->getKey(),
            'locale' => 'en',
            'title' => 'Contact',
            'content_blocks' => [['type' => 'section', 'data' => [
                'layout' => 'hero',
                'fields' => [['type' => 'title', 'value' => 'Updated heading']],
            ]]],
        ]);

        PageTranslation::query()->create([
            'page_id' => $page->getKey(),
            'locale' => 'my',
            'title' => 'Contact',
            'content_blocks' => [['type' => 'section', 'data' => [
                'layout' => 'hero',
                'fields' => [['type' => 'title', 'value' => 'Old heading']],
            ]]],
        ]);

        $this->app->instance(Translator::class, $this->fakeTranslator());

        Livewire::actingAs(User::factory()->admin()->create())
            ->test(EditPage::class, ['record' => $page->getRouteKey()])
            ->callAction('translate', [
                'from' => 'en',
                'to' => ['my'],
                // overwrite deliberately omitted: the action defaults to replacing.
            ])
            ->assertHasNoActionErrors();

        $my = PageTranslation::query()->where('page_id', $page->getKey())->where('locale', 'my')->first();
        $title = $my->blocks()[0]['data']['fields'][0]['value'] ?? null;

        $this->assertSame('[my] Updated heading', $title);
    }

    public function test_the_translate_action_refuses_to_queue_without_a_key(): void
    {
        config(['ai.gemini.key' => '']);

        $page = Page::query()->create([
            'kind' => Page::KIND_PAGE,
            'slug' => 'about',
            'status' => Page::STATUS_PUBLISHED,
            'published_at' => now(),
        ]);

        PageTranslation::query()->create([
            'page_id' => $page->getKey(),
            'locale' => 'en',
            'title' => 'About us',
        ]);

        $editor = User::factory()->admin()->create();

        // A job queued without a key can only ever fail, so the editor is
        // told at the button instead of by a notification after the fact.
        Livewire::actingAs($editor)
            ->test(EditPage::class, ['record' => $page->getRouteKey()])
            ->callAction('translate', [
                'from' => 'en',
                'to' => ['my'],
            ])
            ->assertNotified('Machine translation is not set up');

        $this->assertDatabaseMissing('page_translations', [
            'page_id' => $page->getKey(),
            'locale' => 'my',
        ]);
    }

    public function test_the_translate_button_is_on_the_page_editor(): void
    {
        $page = Page::query()->create([
            'kind' => Page::KIND_PAGE,
            'slug' => 'about',
            'status' => Page::STATUS_DRAFT,
        ]);

        $this->actingAs(User::factory()->admin()->create())
            ->get('/admin/pages/'.$page->getKey().'/edit')
            ->assertOk()
            ->assertSee('Translate');
    }
}
