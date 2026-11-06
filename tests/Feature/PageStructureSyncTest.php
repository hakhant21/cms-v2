<?php

namespace Tests\Feature;

use App\Filament\Resources\Pages\Pages\EditPage;
use App\Models\Language;
use App\Models\Page;
use App\Models\PageTranslation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Sections are managed once, in whichever tab the editor works in. Deleting,
 * adding or reordering a section in ANY language must re-fit every other
 * language automatically — an editor should never have to make the same
 * change three times, nor switch tabs to make it.
 */
class PageStructureSyncTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Language::query()->create(['code' => 'en', 'name' => 'English', 'is_default' => true, 'sort_order' => 1]);
        Language::query()->create(['code' => 'my', 'name' => 'Myanmar', 'sort_order' => 2]);
        Language::forgetCache();

        $page = Page::query()->create([
            'kind' => Page::KIND_PAGE,
            'slug' => 'about',
            'status' => Page::STATUS_PUBLISHED,
            'published_at' => now(),
        ]);

        PageTranslation::query()->create([
            'page_id' => $page->getKey(),
            'locale' => 'en',
            'title' => 'About',
            'content_blocks' => [$this->section('hero', 'Our story'), $this->section('plain', 'Body text')],
        ]);

        PageTranslation::query()->create([
            'page_id' => $page->getKey(),
            'locale' => 'my',
            'title' => 'အကြောင်း',
            'content_blocks' => [$this->section('hero', 'ကျွန်ုပ်တို့၏ ဇာတ်ကြောင်း'), $this->section('plain', 'ကိုယ်ပိုင်း')],
        ]);

        $this->page = $page;
    }

    protected Page $page;

    public function test_deleting_a_section_in_the_main_language_deletes_it_in_every_language(): void
    {
        $this->editor()
            ->set('data.translations.en.content_blocks', [$this->section('hero', 'Our story')])
            ->assertSet('data.translations.my.content_blocks', fn (mixed $blocks): bool => is_array($blocks)
                && count($blocks) === 1
                && $this->layoutAt($blocks, 0) === 'hero'
                // The surviving Burmese block keeps its own words.
                && $this->valueAt($blocks, 0) === 'ကျွန်ုပ်တို့၏ ဇာတ်ကြောင်း');
    }

    public function test_a_new_section_lands_in_every_language_ready_to_translate(): void
    {
        $this->editor()
            ->set('data.translations.en.content_blocks', [
                $this->section('hero', 'Our story'),
                $this->section('plain', 'Body text'),
                $this->section('cta', 'Talk to us'),
            ])
            ->assertSet('data.translations.my.content_blocks', fn (mixed $blocks): bool => is_array($blocks)
                && count($blocks) === 3
                && $this->layoutAt($blocks, 1) === 'plain'
                && $this->layoutAt($blocks, 2) === 'cta'
                // The new section arrives in English; the translated ones stay.
                && $this->valueAt($blocks, 0) === 'ကျွန်ုပ်တို့၏ ဇာတ်ကြောင်း'
                && $this->valueAt($blocks, 1) === 'ကိုယ်ပိုင်း'
                && $this->valueAt($blocks, 2) === 'Talk to us');
    }

    public function test_reordering_the_main_language_reorders_every_translation_with_it(): void
    {
        $this->editor()
            ->set('data.translations.en.content_blocks', [
                $this->section('plain', 'Body text'),
                $this->section('hero', 'Our story'),
            ])
            ->assertSet('data.translations.my.content_blocks', fn (mixed $blocks): bool => is_array($blocks)
                && $this->layoutAt($blocks, 0) === 'plain'
                && $this->valueAt($blocks, 0) === 'ကိုယ်ပိုင်း'
                && $this->layoutAt($blocks, 1) === 'hero'
                && $this->valueAt($blocks, 1) === 'ကျွန်ုပ်တို့၏ ဇာတ်ကြောင်း');
    }

    public function test_editing_only_the_english_words_leaves_the_other_languages_alone(): void
    {
        $blocks = [$this->section('hero', 'A different title'), $this->section('plain', 'Body text')];

        $this->editor()
            ->set('data.translations.en.content_blocks', $blocks)
            ->assertSet('data.translations.my.content_blocks', fn (mixed $current): bool => $this->valueAt((array) $current, 0) === 'ကျွန်ုပ်တို့၏ ဇာတ်ကြောင်း'
                && $this->valueAt((array) $current, 1) === 'ကိုယ်ပိုင်း');
    }

    public function test_an_image_uploaded_in_the_main_language_shows_in_every_language(): void
    {
        // Upload once: the photo belongs to the page, not to a language, so
        // the other tabs receive it while keeping their own words.
        $withImage = $this->section('hero', 'Our story');
        $withImage['data']['fields'][] = ['key' => 'img', 'type' => 'image', 'value' => 'pages/farm.jpg', 'alt' => 'The farm'];

        $this->editor()
            ->set('data.translations.en.content_blocks', [$withImage, $this->section('plain', 'Body text')])
            ->assertSet('data.translations.my.content_blocks', function (mixed $blocks): bool {
                $blocks = array_values((array) $blocks);
                $fields = array_values((array) ($blocks[0]['data']['fields'] ?? []));
                $image = null;

                foreach ($fields as $field) {
                    if (($field['type'] ?? null) === 'image') {
                        $image = $field;
                    }
                }

                return $this->valueAt($blocks, 0) === 'ကျွန်ုပ်တို့၏ ဇာတ်ကြောင်း'
                    && ($image['value'] ?? null) === 'pages/farm.jpg'
                    && ($image['alt'] ?? null) === 'The farm';
            });
    }

    public function test_reordering_in_a_non_default_language_reorders_the_default_too(): void
    {
        // The editor works in Burmese only: the shuffle must reach English
        // without anyone touching the English tab.
        $my = $this->editor()->get('data.translations.my.content_blocks');
        $reversed = array_reverse(array_values($my), true);

        $this->editor()
            ->set('data.translations.my.content_blocks', $reversed)
            ->assertSet('data.translations.en.content_blocks', fn (mixed $blocks): bool => is_array($blocks)
                && $this->layoutAt($blocks, 0) === 'plain'
                && $this->valueAt($blocks, 0) === 'Body text'
                && $this->layoutAt($blocks, 1) === 'hero'
                && $this->valueAt($blocks, 1) === 'Our story');
    }

    public function test_a_section_added_in_a_non_default_language_lands_in_the_default_too(): void
    {
        $my = array_values($this->editor()->get('data.translations.my.content_blocks'));
        $my[] = $this->section('cta', 'ဆက်သွယ်ရန်');

        $this->editor()
            ->set('data.translations.my.content_blocks', $my)
            ->assertSet('data.translations.en.content_blocks', fn (mixed $blocks): bool => is_array($blocks)
                && count($blocks) === 3
                && $this->layoutAt($blocks, 2) === 'cta'
                // The existing English sections keep their own words.
                && $this->valueAt($blocks, 0) === 'Our story'
                && $this->valueAt($blocks, 1) === 'Body text');
    }

    public function test_a_section_deleted_in_a_non_default_language_is_deleted_everywhere(): void
    {
        $my = array_values($this->editor()->get('data.translations.my.content_blocks'));

        $this->editor()
            ->set('data.translations.my.content_blocks', [$my[1]])
            ->assertSet('data.translations.en.content_blocks', fn (mixed $blocks): bool => is_array($blocks)
                && count($blocks) === 1
                && $this->layoutAt($blocks, 0) === 'plain'
                && $this->valueAt($blocks, 0) === 'Body text');
    }

    public function test_typing_words_in_a_non_default_language_leaves_the_default_words_alone(): void
    {
        $my = array_values($this->editor()->get('data.translations.my.content_blocks'));
        // The builder keys its fields by uuid — edit the field that exists,
        // the way typing in the tab does.
        $key = array_key_first($my[0]['data']['fields']);
        $my[0]['data']['fields'][$key]['value'] = 'ကိုယ်တိုင်ရေးသော ခေါင်းစဉ်';

        $this->editor()
            ->set('data.translations.my.content_blocks', $my)
            ->assertSet('data.translations.en.content_blocks', fn (mixed $blocks): bool => $this->valueAt((array) $blocks, 0) === 'Our story'
                && $this->valueAt((array) $blocks, 1) === 'Body text')
            ->assertSet('data.translations.my.content_blocks', fn (mixed $blocks): bool => $this->valueAt((array) $blocks, 0) === 'ကိုယ်တိုင်ရေးသော ခေါင်းစဉ်');
    }

    public function test_a_share_image_uploaded_in_one_tab_lands_in_every_tab(): void
    {
        // The share image belongs to the page, not to a language: uploading it
        // in ANY tab — here Burmese — must show in every other tab. The
        // FileUpload keys its raw state by uuid, so compare the paths.
        $this->editor()
            ->set('data.translations.my.seo.og_image', ['11111111-1111-1111-1111-111111111111' => 'seo/share.jpg'])
            ->assertSet('data.translations.en.seo.og_image', fn (mixed $state): bool => is_array($state)
                && in_array('seo/share.jpg', array_values($state), true));
    }

    public function test_the_search_engine_choice_made_in_one_tab_applies_to_every_tab(): void
    {
        $this->editor()
            ->set('data.translations.en.seo.robots', 'noindex,follow')
            ->assertSet('data.translations.my.seo.robots', 'noindex,follow');
    }

    /** @return Testable */
    private function editor(): mixed
    {
        return Livewire::actingAs(User::factory()->admin()->create())
            ->test(EditPage::class, ['record' => $this->page->getRouteKey()]);
    }

    /**
     * @return array<string, mixed>
     */
    private function section(string $layout, string $title): array
    {
        return [
            'type' => 'section',
            'data' => [
                'layout' => $layout,
                'fields' => [['key' => 'abc', 'type' => 'title', 'value' => $title]],
            ],
        ];
    }

    /** @param  array<array-key, mixed>|mixed  $blocks */
    private function layoutAt(mixed $blocks, int $index): ?string
    {
        $block = array_values((array) $blocks)[$index] ?? null;

        return is_array($block) ? ($block['data']['layout'] ?? null) : null;
    }

    /** @param  array<array-key, mixed>|mixed  $blocks */
    private function valueAt(mixed $blocks, int $index): ?string
    {
        $block = array_values((array) $blocks)[$index] ?? null;
        $field = is_array($block) ? array_values((array) ($block['data']['fields'] ?? []))[0] ?? null : null;

        return is_array($field) ? ($field['value'] ?? null) : null;
    }
}
