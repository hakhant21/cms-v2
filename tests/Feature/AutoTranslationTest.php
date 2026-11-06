<?php

namespace Tests\Feature;

use App\Contracts\Translator;
use App\Filament\Resources\Pages\Pages\CreatePage;
use App\Filament\Resources\Pages\Pages\EditPage;
use App\Models\Language;
use App\Models\Page;
use App\Models\PageTranslation;
use App\Models\User;
use App\Support\Translation\GeminiTranslator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Write once, read everywhere: saving the default language translates the
 * others in the background, while wording an editor writes by hand survives
 * every later save until they explicitly hand it back to the machine.
 *
 * The queue runs synchronously here and the translator is faked, so a save
 * shows its full effect — rows written, flags set — in one request.
 */
class AutoTranslationTest extends TestCase
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

        $this->app->instance(Translator::class, $this->fakeTranslator());
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

    public function test_creating_a_page_in_english_fills_the_other_languages_on_save(): void
    {
        Livewire::actingAs(User::factory()->admin()->create())
            ->test(CreatePage::class)
            ->fillForm([
                'slug' => 'about',
                'translations' => [
                    'en' => [
                        'title' => 'About us',
                        'excerpt' => 'Who we are',
                        'content_blocks' => [['type' => 'section', 'data' => [
                            'layout' => 'plain',
                            'fields' => [['type' => 'title', 'value' => 'Our story']],
                        ]]],
                    ],
                ],
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $page = Page::query()->where('slug', 'about')->first();

        $this->assertNotNull($page);
        $this->assertTrue($page->auto_translate);

        $my = PageTranslation::query()->where('page_id', $page->getKey())->where('locale', 'my')->first();

        $this->assertNotNull($my, 'Saving the English page should translate the other languages without a second visit.');
        $this->assertSame('[my] About us', $my->title);
        $this->assertSame('[my] Who we are', $my->excerpt);
        $this->assertFalse($my->is_manual);
    }

    public function test_saving_an_english_edit_refreshes_the_other_languages(): void
    {
        $page = $this->pageWithLanguages('About', 'Old heading', '[my] Old heading');

        $edited = [['type' => 'section', 'data' => [
            'layout' => 'plain',
            'fields' => [['type' => 'title', 'value' => 'New heading']],
        ]]];

        Livewire::actingAs(User::factory()->admin()->create())
            ->test(EditPage::class, ['record' => $page->getRouteKey()])
            ->set('data.translations.en.title', 'About Ruma')
            ->set('data.translations.en.content_blocks', $edited)
            ->call('save')
            ->assertHasNoFormErrors();

        $my = PageTranslation::query()->where('page_id', $page->getKey())->where('locale', 'my')->first();

        $this->assertSame('[my] About Ruma', $my->title);
        $this->assertSame('[my] New heading', $my->blocks()[0]['data']['fields'][0]['value']);
    }

    public function test_hand_written_wording_survives_this_save_and_the_next(): void
    {
        $page = $this->pageWithLanguages('About', 'Our story', '[my] Our story');

        $editor = User::factory()->admin()->create();

        // The editor writes Burmese by hand instead of letting the machine do it.
        Livewire::actingAs($editor)
            ->test(EditPage::class, ['record' => $page->getRouteKey()])
            ->set('data.translations.my.title', 'ကိုယ်တိုင်ရေးထားသည်')
            ->call('save')
            ->assertHasNoFormErrors();

        $my = PageTranslation::query()->where('page_id', $page->getKey())->where('locale', 'my')->first();

        $this->assertSame('ကိုယ်တိုင်ရေးထားသည်', $my->title, 'The machine must not overwrite what the editor just wrote.');
        $this->assertTrue($my->is_manual);

        // A later save that only touches English still leaves the hand-written
        // language alone — the protection is not just for the current save.
        Livewire::actingAs($editor)
            ->test(EditPage::class, ['record' => $page->getRouteKey()])
            ->set('data.translations.en.title', 'About Ruma')
            ->call('save')
            ->assertHasNoFormErrors();

        $my->refresh();

        $this->assertSame('ကိုယ်တိုင်ရေးထားသည်', $my->title);
        $this->assertTrue($my->is_manual);
        $this->assertSame(
            'About Ruma',
            PageTranslation::query()->where('page_id', $page->getKey())->where('locale', 'en')->value('title'),
        );
    }

    public function test_an_explicit_translate_hands_a_manual_locale_back_to_the_machine(): void
    {
        $page = $this->pageWithLanguages('About', 'Our story', 'ကိုယ်တိုင်ရေးထားသည်', manual: true);

        Livewire::actingAs(User::factory()->admin()->create())
            ->test(EditPage::class, ['record' => $page->getRouteKey()])
            ->callAction('translate', [
                'from' => 'en',
                'to' => ['my'],
                'overwrite' => true,
            ])
            ->assertHasNoActionErrors();

        $my = PageTranslation::query()->where('page_id', $page->getKey())->where('locale', 'my')->first();

        $this->assertSame('[my] About', $my->title);
        $this->assertFalse($my->is_manual, 'Once translated again, the next save may refresh it too.');
    }

    public function test_auto_translate_can_be_switched_off_for_a_page(): void
    {
        Livewire::actingAs(User::factory()->admin()->create())
            ->test(CreatePage::class)
            ->fillForm([
                'slug' => 'about',
                'auto_translate' => false,
                'translations' => [
                    'en' => ['title' => 'About us'],
                ],
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $page = Page::query()->where('slug', 'about')->first();

        $this->assertFalse($page->auto_translate);
        $this->assertDatabaseMissing('page_translations', [
            'page_id' => $page->getKey(),
            'locale' => 'my',
        ]);
    }

    public function test_an_image_change_travels_without_flagging_the_language_as_hand_written(): void
    {
        // Uploading a new photo in English is structure, not wording: the
        // other language receives it and stays machine-owned.
        $page = $this->pageWithLanguages('About', 'Our story', '[my] Our story', image: 'pages/old.jpg');

        $replaced = [['type' => 'section', 'data' => [
            'layout' => 'plain',
            'fields' => [
                ['type' => 'title', 'value' => 'Our story'],
                // FileUpload keeps its raw state as a file map; the form
                // dehydrates it back to the plain path on save.
                ['type' => 'image', 'value' => ['11111111-1111-1111-1111-111111111111' => 'pages/new.jpg']],
            ],
        ]]];

        Livewire::actingAs(User::factory()->admin()->create())
            ->test(EditPage::class, ['record' => $page->getRouteKey()])
            ->set('data.translations.en.content_blocks', $replaced)
            ->call('save')
            ->assertHasNoFormErrors();

        $my = PageTranslation::query()->where('page_id', $page->getKey())->where('locale', 'my')->first();
        $fields = $my->blocks()[0]['data']['fields'];

        $this->assertSame('pages/new.jpg', $fields[1]['value'], 'One upload reaches every language.');
        $this->assertSame('[my] Our story', $fields[0]['value']);
        $this->assertFalse($my->is_manual);
    }

    public function test_reordering_sections_does_not_flag_the_language_as_hand_written(): void
    {
        $page = $this->pageWithTwoSections();

        $blocks = $this->blocksFor($page, 'en');
        $reordered = array_reverse($blocks);

        Livewire::actingAs(User::factory()->admin()->create())
            ->test(EditPage::class, ['record' => $page->getRouteKey()])
            ->set('data.translations.en.content_blocks', $reordered)
            ->call('save')
            ->assertHasNoFormErrors();

        $my = PageTranslation::query()->where('page_id', $page->getKey())->where('locale', 'my')->first();

        $this->assertFalse($my->is_manual, 'Moving a section is structure, not wording — the machine keeps ownership.');
        $this->assertSame('[my] What we do', $my->blocks()[0]['data']['fields'][0]['value']);
        $this->assertSame('[my] Our story', $my->blocks()[1]['data']['fields'][0]['value']);
    }

    public function test_deleting_a_section_does_not_flag_the_language_as_hand_written(): void
    {
        $page = $this->pageWithTwoSections();

        $blocks = $this->blocksFor($page, 'en');

        Livewire::actingAs(User::factory()->admin()->create())
            ->test(EditPage::class, ['record' => $page->getRouteKey()])
            ->set('data.translations.en.content_blocks', [$blocks[1]])
            ->call('save')
            ->assertHasNoFormErrors();

        $my = PageTranslation::query()->where('page_id', $page->getKey())->where('locale', 'my')->first();

        $this->assertFalse($my->is_manual);
        $this->assertCount(1, $my->blocks(), 'Deleting a section once removes it from every language.');
        $this->assertSame('[my] What we do', $my->blocks()[0]['data']['fields'][0]['value']);
    }

    public function test_a_word_edit_after_reordering_still_marks_the_language_manual(): void
    {
        $page = $this->pageWithTwoSections();

        $blocks = $this->blocksFor($page, 'my');
        $reordered = array_reverse($blocks);
        $reordered[0]['data']['fields'][0]['value'] = 'ကိုယ်တိုင်ရေးသော စာသား';

        Livewire::actingAs(User::factory()->admin()->create())
            ->test(EditPage::class, ['record' => $page->getRouteKey()])
            ->set('data.translations.my.content_blocks', $reordered)
            ->call('save')
            ->assertHasNoFormErrors();

        $my = PageTranslation::query()->where('page_id', $page->getKey())->where('locale', 'my')->first();

        $this->assertTrue($my->is_manual, 'A real rewrite still protects the language, even alongside a reorder.');
        $this->assertSame('ကိုယ်တိုင်ရေးသော စာသား', $my->blocks()[0]['data']['fields'][0]['value']);
    }

    public function test_without_a_key_the_save_says_what_is_missing(): void
    {
        config(['ai.gemini.key' => '']);

        // The real service, which is the one that needs the key.
        $this->app->instance(Translator::class, new GeminiTranslator);

        $page = Page::query()->create([
            'kind' => Page::KIND_PAGE,
            'slug' => 'about',
            'status' => Page::STATUS_DRAFT,
        ]);

        PageTranslation::query()->create([
            'page_id' => $page->getKey(),
            'locale' => 'en',
            'title' => 'About',
        ]);

        Livewire::actingAs(User::factory()->admin()->create())
            ->test(EditPage::class, ['record' => $page->getRouteKey()])
            ->call('save')
            ->assertNotified('Auto-translate needs an API key');

        $this->assertDatabaseMissing('page_translations', [
            'page_id' => $page->getKey(),
            'locale' => 'my',
        ]);
    }

    /**
     * A page with two sections, each carrying its own wording per language.
     */
    private function pageWithTwoSections(): Page
    {
        $page = Page::query()->create([
            'kind' => Page::KIND_PAGE,
            'slug' => 'about',
            'status' => Page::STATUS_PUBLISHED,
            'published_at' => now(),
        ]);

        $section = fn (string $heading): array => ['type' => 'section', 'data' => [
            'layout' => 'plain',
            'fields' => [['type' => 'title', 'value' => $heading]],
        ]];

        PageTranslation::query()->create([
            'page_id' => $page->getKey(),
            'locale' => 'en',
            'title' => 'About',
            'content_blocks' => [$section('Our story'), $section('What we do')],
        ]);

        PageTranslation::query()->create([
            'page_id' => $page->getKey(),
            'locale' => 'my',
            'title' => '[my] About',
            'content_blocks' => [$section('[my] Our story'), $section('[my] What we do')],
        ]);

        return $page;
    }

    /** @return array<int, array<string, mixed>> */
    private function blocksFor(Page $page, string $locale): array
    {
        return PageTranslation::query()
            ->where('page_id', $page->getKey())
            ->where('locale', $locale)
            ->first()
            ->blocks();
    }

    /**
     * A page with an English section and a Burmese copy of it.
     */
    private function pageWithLanguages(string $enTitle, string $enHeading, string $myTitle, bool $manual = false, ?string $image = null): Page
    {
        $page = Page::query()->create([
            'kind' => Page::KIND_PAGE,
            'slug' => 'about',
            'status' => Page::STATUS_PUBLISHED,
            'published_at' => now(),
        ]);

        $fields = [['type' => 'title', 'value' => $enHeading]];

        if ($image !== null) {
            $fields[] = ['type' => 'image', 'value' => $image];
        }

        PageTranslation::query()->create([
            'page_id' => $page->getKey(),
            'locale' => 'en',
            'title' => $enTitle,
            'content_blocks' => [['type' => 'section', 'data' => ['layout' => 'plain', 'fields' => $fields]]],
        ]);

        PageTranslation::query()->create([
            'page_id' => $page->getKey(),
            'locale' => 'my',
            'title' => $myTitle,
            'is_manual' => $manual,
            'content_blocks' => [['type' => 'section', 'data' => ['layout' => 'plain', 'fields' => $fields]]],
        ]);

        return $page;
    }
}
