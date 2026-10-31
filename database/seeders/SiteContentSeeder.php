<?php

namespace Database\Seeders;

use App\Models\Image;
use App\Models\Language;
use App\Models\NavigationItem;
use App\Models\Page;
use App\Models\Redirect;
use App\Models\Setting;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

/**
 * Plays the exported site into an empty database.
 *
 * The deployed stack runs MySQL in a container with its own volume, so content
 * cannot travel as a database file the way a SQLite install carried it. It
 * travels as `database/seeders/content/site.json` — written by `php artisan
 * ruma:export`, reviewed as a diff, replayed here — which is also what makes a
 * staging host disposable: wipe the volume, boot, and the site is back.
 *
 * It only fills a database that has no pages. Once an editor is at work, their
 * words are the more recent copy of the truth and overwriting them with the
 * file from the repository would quietly undo real edits, so a site with
 * content in it is left exactly as it is. This is the same safety property the
 * image's old `/seed` snapshot had — restore into an empty volume, never into a
 * live one — expressed in rows instead of bytes, and it is why `db:seed` can run
 * on every container boot.
 */
class SiteContentSeeder extends Seeder
{
    public const FILE = __DIR__.'/content/site.json';

    public function run(): void
    {
        if (Page::withTrashed()->exists()) {
            $this->command?->info('Content already present — nothing imported.');

            return;
        }

        if (! is_file(self::FILE)) {
            $this->command?->warn('No content file to import at ['.self::FILE.'].');

            return;
        }

        $payload = json_decode((string) file_get_contents(self::FILE), true, 512, JSON_THROW_ON_ERROR);

        if (! is_array($payload)) {
            return;
        }

        $images = $this->images($payload['images'] ?? []);
        $pages = $this->pages($payload['pages'] ?? []);
        $this->navigation($payload['navigation'] ?? [], $pages);
        $this->redirects($payload['redirects'] ?? []);

        // Both models read through a static cache so a page view costs one
        // query instead of forty; inside a single CLI process that cache would
        // outlive the rows it was filled with.
        Language::forgetCache();
        Setting::forgetCache();

        $this->command?->info(sprintf(
            'Content imported: %d pages, %d media references, %d navigation links.',
            count($pages),
            $images,
            NavigationItem::count(),
        ));
    }

    /**
     * @param  array<int, mixed>  $rows
     * @return int how many were recorded
     */
    private function images(array $rows): int
    {
        $stored = 0;

        foreach ($rows as $row) {
            if (! is_array($row) || ! filled($row['path'] ?? null)) {
                continue;
            }

            Image::query()->firstOrCreate(
                ['path' => (string) $row['path']],
                [
                    'disk' => (string) ($row['disk'] ?? 'public'),
                    'original_name' => $row['original_name'] ?? null,
                    'alt' => $row['alt'] ?? null,
                    'mime' => $row['mime'] ?? null,
                    'width' => $row['width'] ?? null,
                    'height' => $row['height'] ?? null,
                    'size' => $row['size'] ?? null,
                ],
            );

            $stored++;
        }

        return $stored;
    }

    /**
     * @param  array<int, mixed>  $rows
     * @return array<string, Page> "kind|slug" => the page that now exists
     */
    private function pages(array $rows): array
    {
        $byKey = [];

        foreach ($rows as $row) {
            if (! is_array($row) || ! filled($row['slug'] ?? null) || ! filled($row['kind'] ?? null)) {
                continue;
            }

            $kind = (string) $row['kind'];
            $slug = Str::slug((string) $row['slug']);

            $page = Page::create([
                'kind' => $kind,
                'slug' => $slug,
                'status' => (string) ($row['status'] ?? Page::STATUS_DRAFT),
                'animation' => $row['animation'] ?? null,
                'sort_order' => (int) ($row['sort_order'] ?? 0),
                'published_at' => $row['published_at'] ?? null,
                'featured_image_id' => Image::query()->where('path', (string) ($row['featured'] ?? ''))->value('id'),
            ]);

            // A page that was deleted on the host it came from is deleted here
            // too, so the export is a snapshot and not only a list of live rows.
            if (filled($row['deleted_at'] ?? null)) {
                $page->forceFill(['deleted_at' => $row['deleted_at']])->save();
            }

            foreach ($row['translations'] ?? [] as $translation) {
                if (! is_array($translation) || ! filled($translation['locale'] ?? null)) {
                    continue;
                }

                $page->translations()->create([
                    'locale' => (string) $translation['locale'],
                    'title' => $translation['title'] ?? null,
                    'excerpt' => $translation['excerpt'] ?? null,
                    'seo' => is_array($translation['seo'] ?? null) ? $translation['seo'] : null,
                    'content_blocks' => is_array($translation['blocks'] ?? null) ? $translation['blocks'] : [],
                ]);
            }

            $position = 0;

            foreach ($row['gallery'] ?? [] as $path) {
                $imageId = Image::query()->where('path', (string) $path)->value('id');

                if ($imageId !== null) {
                    $page->galleryImages()->attach((int) $imageId, ['sort_order' => $position++]);
                }
            }

            $byKey[$kind.'|'.$slug] = $page;
        }

        return $byKey;
    }

    /**
     * Both menus, in two passes.
     *
     * A dropdown entry has to know its parent's new id, which does not exist
     * until the parent has been created — so the rows go in first with no
     * parent, then the numbering from the export is used to wire the children.
     *
     * @param  array<int, mixed>  $rows
     * @param  array<string, Page>  $pages
     */
    private function navigation(array $rows, array $pages): void
    {
        /** @var array<int, NavigationItem> $created */
        $created = [];

        foreach ($rows as $row) {
            if (! is_array($row)) {
                continue;
            }

            $page = null;

            if (is_array($row['page'] ?? null) && filled($row['page']['slug'] ?? null)) {
                $page = $pages[($row['page']['kind'] ?? 'page').'|'.Str::slug((string) $row['page']['slug'])] ?? null;
            }

            // A link whose page did not make the trip stays in the menu but is
            // switched off, which is how the admin shows it: greyed out, ready
            // to be repointed, rather than a live 404 for the visitor.
            if (($row['target_type'] ?? NavigationItem::TARGET_PAGE) === NavigationItem::TARGET_PAGE && $page === null) {
                continue;
            }

            $item = NavigationItem::create([
                'placement' => (string) ($row['placement'] ?? NavigationItem::PLACEMENT_HEADER),
                'target_type' => (string) ($row['target_type'] ?? NavigationItem::TARGET_PAGE),
                'page_id' => $page?->getKey(),
                'url' => $row['url'] ?? null,
                'sort_order' => (int) ($row['sort_order'] ?? 0),
                'is_active' => (bool) ($row['is_active'] ?? true),
                'opens_new_tab' => (bool) ($row['opens_new_tab'] ?? false),
            ]);

            foreach ((array) ($row['labels'] ?? []) as $locale => $label) {
                $item->translations()->create([
                    'locale' => (string) $locale,
                    'label' => (string) $label,
                ]);
            }

            $created[(int) ($row['id'] ?? 0)] = $item;
        }

        foreach ($rows as $row) {
            if (! is_array($row) || empty($row['parent'])) {
                continue;
            }

            $child = $created[(int) ($row['id'] ?? 0)] ?? null;
            $parent = $created[(int) $row['parent']] ?? null;

            if ($child !== null && $parent !== null) {
                $child->forceFill(['parent_id' => $parent->getKey()])->save();
            }
        }
    }

    /**
     * @param  array<int, mixed>  $rows
     */
    private function redirects(array $rows): void
    {
        foreach ($rows as $row) {
            if (! is_array($row) || ! filled($row['from_path'] ?? null)) {
                continue;
            }

            Redirect::query()->updateOrCreate(
                ['from_path' => Redirect::cleanPath((string) $row['from_path'])],
                [
                    'to_path' => (string) ($row['to_path'] ?? '/'),
                    'status_code' => (int) ($row['status_code'] ?? Redirect::STATUS_MOVED_PERMANENTLY),
                    'is_active' => (bool) ($row['is_active'] ?? true),
                ],
            );
        }
    }
}
