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
 * Sections are managed once, in the main language tab. Deleting, adding or
 * reordering a section there must re-fit every other language automatically —
 * an editor should never have to remove the same section three times.
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
