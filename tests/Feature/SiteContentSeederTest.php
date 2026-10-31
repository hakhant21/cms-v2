<?php

namespace Tests\Feature;

use App\Models\Image;
use App\Models\NavigationItem;
use App\Models\Page;
use App\Models\Redirect;
use Database\Seeders\SiteContentSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The deployed stack runs MySQL with a volume that starts empty, so the site's
 * content travels as `database/seeders/content/site.json` — written by
 * `php artisan ruma:export`, replayed by this seeder. If the replay drifts, a
 * fresh host comes up with a half-built site, which is the kind of failure that
 * is only noticed after a deploy.
 *
 * The expectations are read from the file itself rather than hard-coded, so
 * updating the content does not mean editing the test.
 */
class SiteContentSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_content_file_fills_an_empty_site(): void
    {
        $payload = $this->payload();

        $this->seed(SiteContentSeeder::class);

        $this->assertSame(count($payload['pages']), Page::query()->withTrashed()->count());
        $this->assertSame(count($payload['images']), Image::query()->count());
        $this->assertSame(count($payload['navigation']), NavigationItem::query()->count());
        $this->assertSame(count($payload['redirects']), Redirect::query()->count());

        // Every locale travelled, and the blocks with them: a page whose
        // translation lost its blocks renders as an empty shell.
        $pages = $payload['pages'];
        $translations = array_sum(array_map(fn (array $page): int => count($page['translations'] ?? []), $pages));

        $this->assertSame($translations, Page::query()->with('translations')->get()->sum(fn (Page $page): int => $page->translations->count()));
        $this->assertGreaterThan(0, $translations);

        $withBlocks = Page::query()->with('translations')->get()
            ->sum(fn (Page $page): int => $page->translations->filter(fn ($translation): bool => $translation->blocks() !== [])->count());

        $this->assertGreaterThan(0, $withBlocks);
    }

    public function test_a_live_site_is_never_overwritten_by_the_file(): void
    {
        $page = Page::query()->create([
            'kind' => Page::KIND_PAGE,
            'slug' => 'written-by-an-editor',
            'status' => Page::STATUS_PUBLISHED,
            'published_at' => now(),
        ]);

        $page->translations()->create(['locale' => 'en', 'title' => 'Written by an editor']);

        $this->seed(SiteContentSeeder::class);

        // Once someone is editing, the database is the newer copy of the truth;
        // the file must not quietly undo their work on every boot.
        $this->assertSame(1, Page::query()->withTrashed()->count());
        $this->assertDatabaseHas('page_translations', ['title' => 'Written by an editor']);
    }

    public function test_the_menus_keep_their_parents_after_the_replay(): void
    {
        $payload = $this->payload();

        $this->seed(SiteContentSeeder::class);

        $withParent = array_filter($payload['navigation'], fn (array $item): bool => $item['parent'] !== null);

        if ($withParent === []) {
            $this->assertTrue(true);

            return;
        }

        // A dropdown entry is created before its parent exists, so this is the
        // part of the replay that can silently flatten a menu.
        $roots = NavigationItem::query()->whereNull('parent_id')->count();
        $children = NavigationItem::query()->whereNotNull('parent_id')->count();

        $this->assertSame(count($withParent), $children);
        $this->assertSame(count($payload['navigation']) - count($withParent), $roots);
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(): array
    {
        $this->assertFileExists(SiteContentSeeder::FILE);

        $payload = json_decode((string) file_get_contents(SiteContentSeeder::FILE), true);

        $this->assertIsArray($payload);

        return $payload;
    }
}
