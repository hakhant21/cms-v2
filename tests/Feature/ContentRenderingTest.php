<?php

namespace Tests\Feature;

use App\Models\Image;
use App\Models\Language;
use App\Models\Page;
use App\Models\Setting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The blocks added for the editors' latest round of requests.
 *
 * Each test builds a page the way the admin does and then reads the rendered
 * HTML, so what is asserted is what a visitor actually receives.
 */
class ContentRenderingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Language::query()->create(['code' => 'en', 'name' => 'English', 'is_default' => true, 'sort_order' => 1]);
        Language::forgetCache();
    }

    /**
     * @param  array<int, array<string, mixed>>  $blocks
     */
    private function page(string $slug, array $blocks, string $title = 'Showcase'): Page
    {
        $page = Page::query()->create([
            'kind' => Page::KIND_PAGE,
            'slug' => $slug,
            'status' => Page::STATUS_PUBLISHED,
            'published_at' => now(),
        ]);

        $page->translations()->create([
            'locale' => 'en',
            'title' => $title,
            'content_blocks' => $blocks,
        ]);

        return $page;
    }

    public function test_a_video_block_turns_a_watch_link_into_a_player(): void
    {
        $this->page('with-video', [
            ['type' => 'video', 'data' => [
                'url' => 'https://www.youtube.com/watch?v=dQw4w9WgXcQ',
                'title' => 'Our farm in the dry season',
            ]],
        ]);

        $this->get('/with-video')
            ->assertOk()
            ->assertSee('youtube-nocookie.com/embed/dQw4w9WgXcQ', false)
            ->assertSee('Our farm in the dry season')
            // The still keeps the panel from being an empty box while the
            // player boots.
            ->assertSee('i.ytimg.com/vi/dQw4w9WgXcQ/hqdefault.jpg', false);
    }

    public function test_a_video_block_prints_nothing_for_a_link_it_cannot_play(): void
    {
        $this->page('bad-video', [
            ['type' => 'video', 'data' => ['url' => 'https://example.com/a-page']],
        ]);

        $this->get('/bad-video')
            ->assertOk()
            ->assertDontSee('<iframe', false);
    }

    public function test_a_section_video_field_also_accepts_a_pasted_link(): void
    {
        $this->page('section-video', [
            ['type' => 'section', 'data' => [
                'layout' => 'plain',
                'fields' => [
                    ['type' => 'title', 'value' => 'Watch the harvest'],
                    ['type' => 'video', 'value' => 'https://youtu.be/dQw4w9WgXcQ'],
                ],
            ]],
        ]);

        $this->get('/section-video')
            ->assertOk()
            ->assertSee('youtube-nocookie.com/embed/dQw4w9WgXcQ', false);
    }

    public function test_feedback_cards_slide_two_at_a_time(): void
    {
        foreach ([['Daw Aye', 'The soil holds water now.'], ['U Kyaw', 'Two more baskets per acre.']] as $index => [$farmer, $quote]) {
            $page = Page::query()->create([
                'kind' => Page::KIND_FEEDBACK,
                'slug' => 'farmer-'.$index,
                'status' => Page::STATUS_PUBLISHED,
                'published_at' => now(),
                'sort_order' => $index,
            ]);

            $page->translations()->create([
                'locale' => 'en',
                'title' => $farmer,
                'content_blocks' => [
                    ['type' => 'feedback_profile', 'data' => [
                        'farmer_name' => $farmer,
                        'quote' => $quote,
                        'location' => 'Nay Pyi Taw',
                    ]],
                ],
            ]);
        }

        $this->page('feedback', [
            ['type' => 'feedback_collection', 'data' => [
                'title' => 'Client Feedback',
                'limit' => 6,
                'display' => 'carousel',
                'per_view' => 2,
                'interval' => 2,
            ]],
        ]);

        $this->get('/feedback')
            ->assertOk()
            // Two cards on screen, a two-second beat, and the name under the
            // quote.
            ->assertSee('data-carousel', false)
            ->assertSee('data-carousel-interval="2000"', false)
            ->assertSee('data-carousel-per-view="2"', false)
            ->assertSee('carousel-slide', false)
            ->assertSee('Daw Aye')
            ->assertSee('The soil holds water now.');
    }

    public function test_the_same_cards_can_be_shown_as_a_grid(): void
    {
        $this->page('feedback-grid', [
            ['type' => 'feedback_collection', 'data' => [
                'title' => 'Client Feedback',
                'display' => 'grid',
            ]],
        ]);

        $this->get('/feedback-grid')
            ->assertOk()
            ->assertDontSee('data-carousel', false);
    }

    public function test_body_copy_is_printed_as_cards_not_bare_paragraphs(): void
    {
        $this->page('cards', [
            ['type' => 'section', 'data' => [
                'layout' => 'plain',
                'fields' => [
                    ['type' => 'title', 'value' => 'How we work'],
                    ['type' => 'paragraph', 'value' => 'This lead sentence belongs to the heading.'],
                    ['type' => 'paragraph', 'value' => 'Second paragraph becomes a card.'],
                    ['type' => 'rich_text', 'value' => '<p>Third block is also a card.</p>'],
                ],
            ]],
        ]);

        $this->get('/cards')
            ->assertOk()
            ->assertSee('This lead sentence belongs to the heading.')
            ->assertSee('Second paragraph becomes a card.')
            ->assertSee('Third block is also a card.', false)
            ->assertSee('content-card', false)
            ->assertSee('data-reveal-group', false);
    }

    public function test_the_footer_shows_only_the_social_networks_an_editor_filled_in(): void
    {
        Setting::put('social.facebook', 'https://www.facebook.com/rumaorganic', 'social');
        Setting::put('social.youtube', 'https://www.youtube.com/@rumaorganic', 'social');
        Setting::forgetCache();

        $this->page('social', [
            ['type' => 'section', 'data' => [
                'layout' => 'plain',
                'fields' => [['type' => 'title', 'value' => 'Hello']],
            ]],
        ]);

        $this->get('/social')
            ->assertOk()
            ->assertSee('https://www.facebook.com/rumaorganic')
            ->assertSee('https://www.youtube.com/@rumaorganic')
            // Instagram has no URL, so its icon must not be printed.
            ->assertDontSee('aria-label="Instagram"', false);
    }

    public function test_the_product_gallery_keeps_the_order_the_editor_chose(): void
    {
        $page = Page::query()->create([
            'kind' => Page::KIND_PRODUCT,
            'slug' => 'gallery-product',
            'status' => Page::STATUS_PUBLISHED,
            'published_at' => now(),
        ]);

        $first = Image::query()->create(['path' => 'products/first.png', 'alt' => 'First']);
        $second = Image::query()->create(['path' => 'products/second.png', 'alt' => 'Second']);
        $third = Image::query()->create(['path' => 'products/third.png', 'alt' => 'Third']);

        $page->galleryImages()->attach([$first->getKey(), $second->getKey(), $third->getKey()]);

        $page->reorderGallery([$third->getKey(), $first->getKey(), $second->getKey()]);

        $this->assertSame(
            [$third->getKey(), $first->getKey(), $second->getKey()],
            $page->galleryImages()->pluck('images.id')->all(),
        );
    }

    public function test_a_picture_opens_in_the_shared_dialog_instead_of_a_new_tab(): void
    {
        // Regression: a lab report used to be handed to the browser as a new
        // tab, which lost the page the visitor was reading. The picture is now
        // a lightbox trigger, and the page carries exactly one dialog for all
        // of them.
        $this->page('reports', [
            ['type' => 'section', 'data' => [
                'layout' => 'plain',
                'fields' => [
                    ['type' => 'title', 'value' => 'Test results'],
                    ['type' => 'image', 'value' => 'reports/2026.png', 'alt' => 'Laboratory report'],
                ],
            ]],
        ]);

        $html = $this->get('/reports')->assertOk()->getContent();

        $this->assertSame(1, substr_count($html, 'id="lightbox"'));
        $this->assertMatchesRegularExpression('/<a\b[^>]*data-lightbox[^>]*>/', $html);

        preg_match('/<a\b[^>]*data-lightbox[^>]*>/', $html, $trigger);

        $this->assertStringNotContainsString('target=', $trigger[0]);
        $this->assertStringContainsString('reports/2026.png', $trigger[0]);
        $this->assertStringContainsString('Laboratory report', $html);
    }
}
