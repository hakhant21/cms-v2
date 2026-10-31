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

    public function test_sections_that_no_longer_match_are_left_alone(): void
    {
        $target = ['content_blocks' => [
            ['type' => 'contact_form', 'data' => ['title' => 'Write to us']],
        ]];

        $result = (new PageTranslator($this->fakeTranslator()))
            ->translatePage($this->sourceState(), $target, 'en', 'my', overwrite: true);

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

    public function test_the_translate_action_fills_the_other_language_tabs(): void
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

        Livewire::actingAs(User::factory()->admin()->create())
            ->test(EditPage::class, ['record' => $page->getRouteKey()])
            ->callAction('translate', [
                'from' => 'en',
                'to' => ['my'],
                'overwrite' => false,
            ])
            ->assertHasNoActionErrors()
            ->assertSet('data.translations.my.title', '[my] About us')
            // The builder re-keys rows (and the fields inside them) by uuid,
            // so the blocks are read as a list and the title field is found by
            // its type rather than by position.
            ->assertSet('data.translations.my.content_blocks', function (mixed $blocks): bool {
                $block = is_array($blocks) ? (array_values($blocks)[0] ?? []) : [];
                $fields = is_array($block['data']['fields'] ?? null) ? $block['data']['fields'] : [];
                $title = null;

                foreach ($fields as $field) {
                    if (($field['type'] ?? null) === 'title') {
                        $title = $field['value'] ?? null;
                    }
                }

                return ($block['type'] ?? null) === 'section'
                    && ($block['data']['layout'] ?? null) === 'split'
                    && $title === '[my] Our story';
            });
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
