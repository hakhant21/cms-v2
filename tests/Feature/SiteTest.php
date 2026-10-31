<?php

namespace Tests\Feature;

use App\Livewire\ContactForm;
use App\Models\ContactMessage;
use App\Models\Language;
use App\Models\NavigationItem;
use App\Models\Page;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * The public site is entirely driven by `pages` rows, so these tests create
 * content the way the admin does and then ask for it over HTTP.
 */
class SiteTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Language::query()->create(['code' => 'en', 'name' => 'English', 'is_default' => true, 'sort_order' => 1]);
        Language::query()->create(['code' => 'my', 'name' => 'Myanmar', 'sort_order' => 2]);
        Language::forgetCache();

        $this->page('home', 'Home', 'ပင်မ', [
            ['type' => 'section', 'data' => ['layout' => 'plain', 'fields' => [
                ['type' => 'title', 'value' => 'Welcome home'],
            ]]],
        ]);

        $this->page('about', 'About us', 'ကျွန်ုပ်တို့အကြောင်း', [
            ['type' => 'section', 'data' => ['layout' => 'plain', 'fields' => [
                ['type' => 'title', 'value' => 'The company story'],
            ]]],
        ]);
    }

    /**
     * A published page of the kind a collection gathers.
     */
    private function project(string $slug, string $title): Page
    {
        $page = Page::query()->create([
            'kind' => Page::KIND_PROJECT,
            'slug' => $slug,
            'status' => Page::STATUS_PUBLISHED,
            'published_at' => now(),
        ]);

        $page->translations()->create(['locale' => 'en', 'title' => $title, 'content_blocks' => []]);

        return $page;
    }

    /**
     * @param  array<int, array<string, mixed>>  $blocks
     */
    private function page(string $slug, string $english, string $burmese, array $blocks = [], string $status = Page::STATUS_PUBLISHED): Page
    {
        $page = Page::query()->create([
            'kind' => Page::KIND_PAGE,
            'slug' => $slug,
            'status' => $status,
            'published_at' => now(),
        ]);

        $page->translations()->create([
            'locale' => 'en',
            'title' => $english,
            'content_blocks' => $blocks,
        ]);

        $page->translations()->create([
            'locale' => 'my',
            'title' => $burmese,
            'content_blocks' => $blocks,
        ]);

        return $page;
    }

    public function test_the_root_url_renders_the_home_page(): void
    {
        $this->get('/')
            ->assertOk()
            ->assertSee('Welcome home')
            ->assertSee('Home');
    }

    public function test_a_page_is_rendered_by_its_slug(): void
    {
        $this->get('/about')->assertOk()->assertSee('The company story');
    }

    public function test_a_prefixed_url_renders_the_same_page_in_that_language(): void
    {
        // Regression: the `{locale}` segment used to be handed to the
        // controller as the slug, so every translated URL rendered the home
        // page instead of the page that was asked for.
        $this->get('/my/about')
            ->assertOk()
            ->assertSee('The company story')
            ->assertSee('ကျွန်ုပ်တို့အကြောင်း')
            ->assertDontSee('Welcome home');
    }

    public function test_a_bare_locale_prefix_renders_the_home_page_in_that_language(): void
    {
        $this->get('/my')->assertOk()->assertSee('ပင်မ');
    }

    public function test_the_default_language_prefix_redirects_to_the_root(): void
    {
        $this->get('/en/about')->assertRedirect('/about');
    }

    public function test_an_unknown_language_prefix_is_not_found(): void
    {
        $this->get('/zz/about')->assertNotFound();
    }

    public function test_a_draft_page_is_hidden_from_visitors(): void
    {
        $this->page('secret', 'Secret', 'လျှို့ဝှက်', [], Page::STATUS_DRAFT);

        $this->get('/secret')->assertNotFound();
    }

    public function test_an_unknown_slug_is_not_found(): void
    {
        $this->get('/nope')
            ->assertNotFound()
            ->assertSee(__('ui.errors.not_found_title'))
            ->assertSee(__('ui.errors.go_home'));
    }

    public function test_the_not_found_page_offers_the_links_from_the_admin(): void
    {
        $about = Page::query()->where('slug', 'about')->firstOrFail();

        $link = NavigationItem::query()->create([
            'placement' => NavigationItem::PLACEMENT_HEADER,
            'target_type' => NavigationItem::TARGET_PAGE,
            'page_id' => $about->getKey(),
            'sort_order' => 1,
        ]);

        $link->translations()->create(['locale' => 'en', 'label' => 'Our story']);

        $this->get('/nope')->assertNotFound()->assertSee('Our story');
    }

    public function test_an_editor_can_preview_a_draft_in_any_language(): void
    {
        $page = $this->page('secret', 'Secret plan', 'လျှို့ဝှက်', [
            ['type' => 'section', 'data' => ['layout' => 'plain', 'fields' => [
                ['type' => 'title', 'value' => 'Only editors see this'],
            ]]],
        ], Page::STATUS_DRAFT);

        $this->get("/preview/{$page->getKey()}")->assertRedirect('/admin/login');

        $this->actingAs(User::factory()->admin()->create())
            ->get("/preview/{$page->getKey()}?locale=my")
            ->assertOk()
            ->assertSee('Only editors see this')
            ->assertSee('Preview');
    }

    public function test_the_sitemap_lists_the_default_and_prefixed_urls(): void
    {
        $this->get('/sitemap.xml')
            ->assertOk()
            ->assertHeader('Content-Type', 'application/xml; charset=UTF-8')
            ->assertSee(url('/about'), false)
            ->assertSee(url('/my/about'), false);
    }

    public function test_the_header_uses_the_navigation_items_from_the_admin(): void
    {
        $about = Page::query()->where('slug', 'about')->firstOrFail();

        $link = NavigationItem::query()->create([
            'placement' => NavigationItem::PLACEMENT_HEADER,
            'target_type' => NavigationItem::TARGET_PAGE,
            'page_id' => $about->getKey(),
            'sort_order' => 1,
        ]);

        $link->translations()->create(['locale' => 'en', 'label' => 'Our story']);

        $this->get('/')->assertOk()->assertSee('Our story');
    }

    public function test_a_switched_off_navigation_link_is_not_rendered(): void
    {
        $about = Page::query()->where('slug', 'about')->firstOrFail();

        $parent = NavigationItem::query()->create([
            'placement' => NavigationItem::PLACEMENT_HEADER,
            'target_type' => NavigationItem::TARGET_URL,
            'url' => '#',
            'sort_order' => 1,
        ]);

        $parent->translations()->create(['locale' => 'en', 'label' => 'Resources']);

        $child = NavigationItem::query()->create([
            'parent_id' => $parent->getKey(),
            'placement' => NavigationItem::PLACEMENT_HEADER,
            'target_type' => NavigationItem::TARGET_PAGE,
            'page_id' => $about->getKey(),
            'sort_order' => 1,
            'is_active' => false,
        ]);

        $child->translations()->create(['locale' => 'en', 'label' => 'Hidden child']);

        // Regression: children were rendered whatever their state, so hiding
        // a sub-link in the admin changed nothing on the site.
        $this->get('/')->assertOk()->assertSee('Resources')->assertDontSee('Hidden child');
    }

    public function test_a_collection_block_lists_the_published_entries_of_its_kind(): void
    {
        $this->page('news', 'News', 'သတင်း', [
            ['type' => 'post_collection', 'data' => ['limit' => 6]],
        ]);

        foreach (['harvest' => 'Harvest season', 'market' => 'Market day'] as $slug => $title) {
            Page::query()->create([
                'kind' => Page::KIND_NEWS,
                'slug' => $slug,
                'status' => Page::STATUS_PUBLISHED,
                'published_at' => now(),
            ])->translations()->create([
                'locale' => 'en',
                'title' => $title,
                'content_blocks' => [],
            ]);
        }

        // A draft never reaches the listing, which is the whole point of the
        // gatherers reading the library at render time.
        Page::query()->create([
            'kind' => Page::KIND_NEWS,
            'slug' => 'unfinished',
            'status' => Page::STATUS_DRAFT,
        ])->translations()->create(['locale' => 'en', 'title' => 'Not ready', 'content_blocks' => []]);

        $this->get('/news')
            ->assertOk()
            ->assertSee('Harvest season')
            ->assertSee('Market day')
            ->assertDontSee('Not ready');
    }

    public function test_the_browse_all_link_follows_the_editor_and_otherwise_stays_out(): void
    {
        $this->project('drip', 'Drip lines');

        $this->page('projects', 'Projects', 'ပရောဂျက်များ', [
            ['type' => 'post_collection', 'data' => [
                'title' => 'In motion', 'source' => Page::KIND_PROJECT, 'url' => '/about',
            ]],
        ]);

        // Regression: the link used to be hard-wired to `/news`, so a project
        // list invited visitors to a 404 — and a collection with nowhere to go
        // still showed one.
        $this->get('/projects')
            ->assertOk()
            ->assertSee('Drip lines')
            ->assertSee('href="/about"', false)
            ->assertDontSee('/news');

        $this->page('quiet', 'Quiet', 'တိတ်ဆိတ်', [
            ['type' => 'post_collection', 'data' => [
                'title' => 'In motion', 'source' => Page::KIND_PROJECT,
            ]],
        ]);

        $this->get('/quiet')
            ->assertOk()
            ->assertSee('Drip lines')
            ->assertDontSee('Browse all');
    }

    public function test_theme_colours_from_the_admin_are_written_into_the_page(): void
    {
        Setting::put('theme.primary', '#123456', 'theme');

        $this->get('/')->assertOk()->assertSee('--site-primary: #123456;', false);
    }

    public function test_a_page_describes_itself_with_structured_data(): void
    {
        $this->get('/about')
            ->assertOk()
            ->assertSee('"@type":"WebPage"', false)
            ->assertSee('"@type":"BreadcrumbList"', false)
            ->assertSee('"@type":"WebSite"', false);
    }

    public function test_an_article_is_published_as_an_article_not_a_web_page(): void
    {
        Page::query()->create([
            'kind' => Page::KIND_NEWS,
            'slug' => 'harvest',
            'status' => Page::STATUS_PUBLISHED,
            'published_at' => now(),
        ])->translations()->create([
            'locale' => 'en',
            'title' => 'Harvest season',
            'content_blocks' => [],
        ]);

        $response = $this->get('/news/harvest')->assertOk()->assertSee('"@type":"Article"', false);

        // There is no `/news` page yet, so the trail must not point at one.
        $this->assertSame(2, substr_count($response->getContent(), '"position":'));
    }

    public function test_the_breadcrumb_only_names_a_section_that_exists(): void
    {
        $this->page('news', 'News', 'သတင်း');

        Page::query()->create([
            'kind' => Page::KIND_NEWS,
            'slug' => 'harvest',
            'status' => Page::STATUS_PUBLISHED,
            'published_at' => now(),
        ])->translations()->create([
            'locale' => 'en',
            'title' => 'Harvest season',
            'content_blocks' => [],
        ]);

        $this->get('/news/harvest')
            ->assertOk()
            ->assertSee('"position":3', false)
            ->assertSee(url('/news'), false);
    }

    public function test_the_contact_form_stores_a_message_and_notifies(): void
    {
        // The component rejects submissions that arrive within two seconds of
        // rendering, which is exactly what a test does, so let time pass
        // between mounting and submitting.
        $component = Livewire::test(ContactForm::class)
            ->set('name', 'Daw Aye')
            ->set('email', 'aye@example.com')
            ->set('subject', 'Fertiliser for 4 acres')
            ->set('message', 'Which product suits monsoon rice on clay soil?');

        $this->travel(3)->seconds();

        $component->call('submit')->assertSet('sent', true);

        $this->assertSame(1, ContactMessage::query()->count());
        $this->assertSame('Daw Aye', ContactMessage::query()->first()->name);
    }

    public function test_the_contact_form_rejects_an_empty_submission(): void
    {
        Livewire::test(ContactForm::class)
            ->call('submit')
            ->assertHasErrors(['name', 'email', 'subject', 'message']);

        $this->assertSame(0, ContactMessage::query()->count());
    }

    public function test_the_honeypot_silently_swallows_a_bot_submission(): void
    {
        Livewire::test(ContactForm::class)
            ->set('name', 'Bot')
            ->set('email', 'bot@example.com')
            ->set('subject', 'Cheap pills')
            ->set('message', 'Buy now, lots of cheap things for sale.')
            ->set('website', 'http://spam.example')
            ->call('submit')
            ->assertSet('sent', true);

        $this->assertSame(0, ContactMessage::query()->count());
    }
}
