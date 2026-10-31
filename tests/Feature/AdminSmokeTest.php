<?php

namespace Tests\Feature;

use App\Blocks\BlockRegistry;
use App\Models\Image;
use App\Models\Language;
use App\Models\Page;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Every admin screen must render for an editor.
 *
 * Filament renders its forms server-side, so a broken block schema, a missing
 * field type or a bad relationship shows up here as a 500 rather than as a
 * blank panel in production.
 */
class AdminSmokeTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        Language::query()->create(['code' => 'en', 'name' => 'English', 'is_default' => true, 'sort_order' => 1]);
        Language::query()->create(['code' => 'my', 'name' => 'Myanmar', 'sort_order' => 2]);
        Language::forgetCache();

        $this->admin = User::factory()->admin()->create();
    }

    public static function adminScreens(): array
    {
        return [
            'dashboard' => ['/admin'],
            'pages list' => ['/admin/pages'],
            'page create' => ['/admin/pages/create'],
            'navigation list' => ['/admin/navigation-items'],
            'navigation create' => ['/admin/navigation-items/create'],
            'media list' => ['/admin/images'],
            'media create' => ['/admin/images/create'],
            'messages list' => ['/admin/contact-messages'],
            'languages list' => ['/admin/languages'],
            'languages create' => ['/admin/languages/create'],
            'users list' => ['/admin/users'],
            'users create' => ['/admin/users/create'],
            'settings' => ['/admin/settings'],
            'redirects list' => ['/admin/redirects'],
            'redirects create' => ['/admin/redirects/create'],
        ];
    }

    #[DataProvider('adminScreens')]
    public function test_an_editor_can_open_every_admin_screen(string $url): void
    {
        $this->actingAs($this->admin)->get($url)->assertOk();
    }

    public function test_the_page_editor_offers_every_registered_block(): void
    {
        $page = Page::query()->create([
            'kind' => Page::KIND_PAGE,
            'slug' => 'about',
            'status' => Page::STATUS_PUBLISHED,
        ]);

        $page->translations()->create(['locale' => 'en', 'title' => 'About']);

        $response = $this->actingAs($this->admin)->get("/admin/pages/{$page->getKey()}/edit");

        $response->assertOk();

        $registry = app(BlockRegistry::class);

        foreach (array_keys($registry->pickerLabels()) as $key) {
            $response->assertSee($key, false);
        }
    }

    public function test_a_guest_is_sent_to_the_admin_login(): void
    {
        $this->get('/admin/pages')->assertRedirect('/admin/login');
    }

    public function test_the_media_editor_opens_a_file_whose_name_was_not_valid_utf8(): void
    {
        // Regression: one bad byte in a file name made json_encode() refuse the
        // Livewire snapshot, which turned this screen into a 500.
        $image = Image::query()->create([
            'disk' => 'public',
            'path' => 'https://cdn.example.com/Top-quality.jpg',
            'original_name' => "Top-\xE2\x5F\x5F-quality.jpg",
        ]);

        $this->assertTrue(mb_check_encoding((string) $image->fresh()->original_name, 'UTF-8'));

        $this->actingAs($this->admin)
            ->get("/admin/images/{$image->getKey()}/edit")
            ->assertOk();
    }
}
