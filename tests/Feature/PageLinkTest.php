<?php

namespace Tests\Feature;

use App\Blocks\PageLinks;
use App\Filament\Resources\Pages\Pages\EditPage;
use App\Models\Language;
use App\Models\Page;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * A block button points at a page, and the admin checks that choice against the
 * pages that really exist. The routes here are generic — `/about` is a row in
 * `pages`, not a named route — so the destination list has to be read from the
 * content. A value missing from that list is worse than a blank field: it
 * reports "the selected destination is invalid" and blocks the whole page from
 * being saved, which is what these tests guard.
 */
class PageLinkTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Language::query()->create(['code' => 'en', 'name' => 'English', 'is_default' => true, 'sort_order' => 1]);
        Language::forgetCache();
    }

    public function test_the_destinations_are_the_published_pages(): void
    {
        $this->publish(Page::KIND_PAGE, 'home', 'Home');
        $this->publish(Page::KIND_PAGE, 'about', 'About us');
        $this->publish(Page::KIND_PRODUCT, 'florata', 'Florata');
        $this->publish(Page::KIND_NEWS, 'farmers', 'Harvest season');

        $draft = $this->publish(Page::KIND_PAGE, 'unreleased', 'A draft');
        $draft->update(['status' => Page::STATUS_DRAFT]);

        $options = PageLinks::options();

        $this->assertSame('Home', $options['/']);
        $this->assertSame('About us', $options['/about']);
        $this->assertSame('Florata — product', $options['/products/florata']);
        $this->assertSame('Harvest season — post', $options['/news/farmers']);

        $this->assertArrayNotHasKey('/unreleased', $options, 'a draft has no public address to link to');
        $this->assertArrayNotHasKey('/home', $options, 'the front door is offered once, at /');
        $this->assertArrayNotHasKey('/about/', $options);

        // The whole list for this content, in the order the picker shows it:
        // the front door, then pages, then products, then posts.
        $this->assertSame(['/about', '/products/florata', '/news/farmers'], array_slice(array_keys($options), 1));
    }

    public function test_a_link_whose_page_is_gone_stays_on_the_list(): void
    {
        $this->publish(Page::KIND_PAGE, 'about', 'About us');

        $options = PageLinks::options('/news/harvest-season-2019');

        $this->assertArrayHasKey('/news/harvest-season-2019', $options);
        $this->assertStringContainsString('no longer listed', $options['/news/harvest-season-2019']);
        $this->assertArrayHasKey('/about', $options, 'the rest of the list is unaffected');
    }

    public function test_an_editor_can_save_a_button_pointed_at_a_page(): void
    {
        $this->publish(Page::KIND_PAGE, 'home', 'Home');
        $this->publish(Page::KIND_PAGE, 'contact', 'Contact us');
        $page = $this->publish(Page::KIND_PAGE, 'about', 'About us');

        Livewire::actingAs(User::factory()->admin()->create())
            ->test(EditPage::class, ['record' => $page->getRouteKey()])
            ->set('data.translations.en.content_blocks', [$this->ctaSection('/contact')])
            ->call('save')
            ->assertHasNoErrors();

        $blocks = $page->refresh()->translation('en')?->content_blocks ?? [];

        $this->assertSame(
            '/contact',
            array_values(array_values($blocks)[0]['data']['fields'])[0]['url'] ?? null,
        );
    }

    /**
     * @return array{type: string, data: array<string, mixed>}
     */
    private function ctaSection(string $destination): array
    {
        return [
            'type' => 'section',
            'data' => [
                'layout' => 'cta',
                'fields' => [
                    ['key' => 'cta-button', 'type' => 'button', 'value' => 'Talk to our team', 'url' => $destination],
                ],
            ],
        ];
    }

    private function publish(string $kind, string $slug, string $title): Page
    {
        $page = Page::query()->create([
            'kind' => $kind,
            'slug' => $slug,
            'status' => Page::STATUS_PUBLISHED,
        ]);

        $page->translations()->create(['locale' => 'en', 'title' => $title]);

        return $page->unsetRelation('translations')->fresh();
    }
}
