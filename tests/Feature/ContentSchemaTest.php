<?php

namespace Tests\Feature;

use App\Models\ContactMessage;
use App\Models\Language;
use App\Models\NavigationItem;
use App\Models\Page;
use App\Models\Setting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Guards the content schema decisions the whole CMS is built on. */
class ContentSchemaTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Language::query()->create(['code' => 'en', 'name' => 'English', 'is_default' => true, 'sort_order' => 1]);
        Language::query()->create(['code' => 'my', 'name' => 'Myanmar', 'sort_order' => 2]);
        Language::forgetCache();
    }

    public function test_page_translations_fall_back_to_the_default_locale(): void
    {
        $page = Page::query()->create([
            'kind' => Page::KIND_PAGE,
            'slug' => 'about',
            'status' => Page::STATUS_PUBLISHED,
        ]);

        $page->translations()->create([
            'locale' => 'en',
            'title' => 'About us',
            'content_blocks' => [['type' => 'section', 'data' => []]],
        ]);

        $this->assertSame('About us', $page->localizedTitle('my'));
        $this->assertCount(1, $page->localizedContentBlocks('my'));
    }

    public function test_the_same_slug_may_exist_for_different_kinds(): void
    {
        Page::query()->create(['kind' => Page::KIND_PAGE, 'slug' => 'harvest']);
        Page::query()->create(['kind' => Page::KIND_NEWS, 'slug' => 'harvest']);

        $this->assertSame(2, Page::query()->count());
    }

    public function test_a_trashed_page_is_hidden_but_recoverable(): void
    {
        $page = Page::query()->create(['kind' => Page::KIND_PAGE, 'slug' => 'legacy']);
        $page->delete();

        $this->assertSame(0, Page::query()->count());
        $this->assertSame(1, Page::withTrashed()->count());
    }

    public function test_navigation_tree_exposes_localized_labels_and_children(): void
    {
        $parent = NavigationItem::query()->create([
            'placement' => NavigationItem::PLACEMENT_HEADER,
            'target_type' => NavigationItem::TARGET_URL,
            'url' => '/products',
            'sort_order' => 1,
        ]);
        $parent->translations()->create(['locale' => 'en', 'label' => 'Products']);
        $parent->translations()->create(['locale' => 'my', 'label' => 'ထုတ်ကုန်များ']);

        $child = NavigationItem::query()->create([
            'parent_id' => $parent->id,
            'target_type' => NavigationItem::TARGET_URL,
            'url' => '/products/rice',
            'sort_order' => 2,
        ]);
        $child->translations()->create(['locale' => 'en', 'label' => 'Rice']);

        $tree = NavigationItem::tree(NavigationItem::PLACEMENT_HEADER);

        $this->assertCount(1, $tree);
        $this->assertSame('ထုတ်ကုန်များ', $tree->first()->localizedLabel('my'));
        $this->assertSame('/products/rice', $tree->first()->children->first()->href());
    }

    public function test_settings_are_cast_to_their_declared_type(): void
    {
        Setting::put('theme.primary', '#166534', 'theme');
        Setting::put('mail.enabled', true, 'mail');
        Setting::put('seo.page_size', 12, 'seo');

        $this->assertSame('#166534', Setting::get('theme.primary'));
        $this->assertTrue(Setting::bool('mail.enabled'));
        $this->assertSame(12, Setting::integer('seo.page_size'));
    }

    public function test_marking_a_message_read_advances_its_status(): void
    {
        $message = ContactMessage::query()->create([
            'name' => 'Aung',
            'email' => 'aung@example.com',
            'message' => 'Hello',
        ]);

        $this->assertSame(ContactMessage::STATUS_NEW, $message->status);

        $message->markRead();

        $this->assertSame(ContactMessage::STATUS_READ, $message->fresh()->status);
        $this->assertNotNull($message->fresh()->read_at);
    }
}
