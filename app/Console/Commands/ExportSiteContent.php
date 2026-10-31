<?php

namespace App\Console\Commands;

use App\Models\Image;
use App\Models\NavigationItem;
use App\Models\Page;
use App\Models\Redirect;
use Illuminate\Console\Command;

/**
 * Writes the site's content out as a portable file.
 *
 * The deployed stack runs on MySQL in a container whose data volume starts
 * empty, so the content an editor wrote on another host has to arrive as data
 * rather than as a database file: `database/seeders/content/site.json` is that
 * file, and `SiteContentSeeder` plays it back into whatever engine the install
 * uses. It is deliberately readable — it is a text file in the repository, so a
 * pull request can show a wording change, and `git diff` is the backup audit.
 *
 * Everything is referenced by a stable business key instead of an id: images by
 * their storage path, navigation targets by kind + slug, translations by locale.
 * That is what lets the same file seed a database whose auto-increments are
 * nothing like the ones it was written from.
 */
class ExportSiteContent extends Command
{
    protected $signature = 'ruma:export
        {--path= : Where to write the content file}
        {--json : Print to stdout instead of writing a file}';

    protected $description = 'Write pages, media references, navigation and redirects to a portable content file';

    /** Where the seeder looks for the file, and where the export puts it. */
    public const DEFAULT_PATH = 'database/seeders/content/site.json';

    public function handle(): int
    {
        $payload = [
            'version' => 1,
            'exported_at' => now()->toIso8601String(),
            'images' => $this->images(),
            'pages' => $this->pages(),
            'navigation' => $this->navigation(),
            'redirects' => $this->redirects(),
        ];

        $json = json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)."\n";

        if ($this->option('json')) {
            $this->output->write($json);

            return self::SUCCESS;
        }

        $path = (string) ($this->option('path') ?: base_path(self::DEFAULT_PATH));

        if (! is_dir($dir = dirname($path))) {
            mkdir($dir, 0o755, true);
        }

        file_put_contents($path, $json);

        $this->components->info(sprintf(
            'Exported %d pages, %d media references, %d navigation links and %d redirects to %s.',
            count($payload['pages']),
            count($payload['images']),
            count($payload['navigation']),
            count($payload['redirects']),
            $this->relative($path),
        ));

        if (! str_ends_with($path, '.json')) {
            $this->components->warn("The target does not end in .json, so the seeder will not find it at [{$this->relative($path)}].");
        }

        return self::SUCCESS;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function images(): array
    {
        return Image::query()->orderBy('path')->get()
            ->map(fn (Image $image): array => [
                'path' => $image->path,
                'disk' => $image->disk,
                'original_name' => $image->original_name,
                'alt' => $image->alt,
                'mime' => $image->mime,
                'width' => $image->width,
                'height' => $image->height,
                'size' => $image->size,
            ])
            ->all();
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function pages(): array
    {
        return Page::query()
            ->with(['translations', 'featuredImage', 'galleryImages'])
            ->orderBy('kind')
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get()
            ->map(fn (Page $page): array => [
                'kind' => $page->kind,
                'slug' => $page->slug,
                'status' => $page->status,
                'animation' => $page->animation,
                'sort_order' => (int) $page->sort_order,
                'published_at' => $page->published_at?->toIso8601String(),
                'deleted_at' => $page->deleted_at?->toIso8601String(),
                // Referenced by path: ids mean nothing on another engine.
                'featured' => $page->featuredImage?->path,
                'gallery' => $page->galleryImages->pluck('path')->all(),
                'translations' => $page->translations
                    ->map(fn ($translation): array => [
                        'locale' => $translation->locale,
                        'title' => $translation->title,
                        'excerpt' => $translation->excerpt,
                        'seo' => $translation->seo,
                        'blocks' => $translation->blocks(),
                    ])
                    ->all(),
            ])
            ->all();
    }

    /**
     * Both menus, as flat rows.
     *
     * `id` and `parent` are the export's own numbering, not database ids: the
     * seeder creates the rows in one pass and wires the children in a second,
     * so a dropdown survives without depending on auto-increments matching.
     *
     * @return list<array<string, mixed>>
     */
    private function navigation(): array
    {
        $items = NavigationItem::query()
            ->with(['translations', 'page'])
            ->orderBy('placement')
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get()
            ->values();

        // The export numbers the rows from 1, so a child has to say which
        // number its parent got rather than carry a database id.
        $numbers = $items->mapWithKeys(fn (NavigationItem $item, int $index): array => [$item->id => $index + 1]);

        return $items->map(function (NavigationItem $item, int $index) use ($numbers): array {
            return [
                'id' => $index + 1,
                'parent' => $item->parent_id === null ? null : $numbers->get((int) $item->parent_id),
                'placement' => $item->placement,
                'target_type' => $item->target_type,
                'page' => $item->page === null
                    ? null
                    : ['kind' => $item->page->kind, 'slug' => $item->page->slug],
                'url' => $item->url,
                'sort_order' => (int) $item->sort_order,
                'is_active' => (bool) $item->is_active,
                'opens_new_tab' => (bool) $item->opens_new_tab,
                'labels' => $item->translations
                    ->mapWithKeys(fn ($translation): array => [$translation->locale => $translation->label])
                    ->all(),
            ];
        })->all();
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function redirects(): array
    {
        return Redirect::query()->orderBy('from_path')->get()
            ->map(fn (Redirect $redirect): array => [
                'from_path' => $redirect->from_path,
                'to_path' => $redirect->to_path,
                'status_code' => (int) $redirect->status_code,
                'is_active' => (bool) $redirect->is_active,
            ])
            ->all();
    }

    private function relative(string $path): string
    {
        return str_starts_with($path, $prefix = base_path().'/') ? substr($path, strlen($prefix)) : $path;
    }
}
