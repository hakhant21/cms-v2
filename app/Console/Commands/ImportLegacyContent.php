<?php

namespace App\Console\Commands;

use App\Models\Image;
use App\Models\Language;
use App\Models\NavigationItem;
use App\Models\Page;
use App\Models\PageTranslation;
use App\Models\Redirect;
use App\Models\Setting;
use App\Support\Text;
use Database\Seeders\FooterNavigationSeeder;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Imports the legacy site into the new schema.
 *
 * The old database stored one row per page with three locale columns and a
 * separate `blocks` table joined through `block_page`. This command reads that
 * shape with a temporary read-only connection and writes the normalised shape
 * the new admin edits: one page row, one translation row per locale, and the
 * same block payloads the editor produces today.
 *
 * It is safe to re-run with --fresh, which clears content first but leaves
 * users and passwords alone.
 */
class ImportLegacyContent extends Command
{
    protected $signature = 'ruma:import
        {path : Path to the legacy sqlite database}
        {--fresh : Delete existing content before importing}
        {--force : Do not ask for confirmation}';

    protected $description = 'Import pages, blocks, navigation, media and settings from the legacy database';

    /** The locales the legacy database stores columns for. */
    private const LOCALES = ['en', 'my', 'ru'];

    /**
     * Content the site no longer has, so the importer must not bring it back.
     *
     * News and FAQ were removed from this site — the pages, the entries and the
     * blocks that listed them. Importing them again would recreate a section
     * with no listing page behind it, which is what the removal was meant to
     * clean up. Legacy rows of these kinds are skipped by name.
     */
    private const SKIPPED_KINDS = [Page::KIND_NEWS, Page::KIND_FAQ];

    /** @var array<int, int> legacy image id => new image id */
    private array $imageMap = [];

    /** @var array<int, Page> legacy page id => new page */
    private array $pageMap = [];

    /** @var array<int, int> legacy page id => featured image id */
    private array $featuredByPage = [];

    /** @var array<int, array<int, int>> legacy page id => gallery image ids */
    private array $galleryByPage = [];

    /** @var array<int, string> menu URLs that resolved to no page */
    private array $unreachableLinks = [];

    /** @var array<int, string> legacy addresses deliberately not imported */
    private array $skippedKinds = [];

    public function handle(): int
    {
        $path = (string) $this->argument('path');

        if (! is_file($path)) {
            $this->components->error("Legacy database not found at [{$path}].");

            return self::FAILURE;
        }

        if ($this->option('fresh') && ! $this->option('force')
            && ! $this->components->confirm('This deletes all existing pages, media and settings. Continue?', false)) {
            return self::FAILURE;
        }

        $this->connect($path);

        DB::transaction(function (): void {
            if ($this->option('fresh')) {
                $this->clear();
            }

            $this->importLanguages();
            $this->importSettings();
            $this->importImages();
            $this->importPages();
            $this->importArchives();
            $this->importContactMap();
            $this->importNavigation();
            $this->importRedirects();
        });

        Language::forgetCache();
        Setting::forgetCache();

        $this->components->info(sprintf(
            'Imported %d pages, %d media items and %d navigation links.',
            Page::count(),
            Image::count(),
            NavigationItem::count(),
        ));

        if ($this->skippedKinds !== []) {
            $this->components->warn(sprintf(
                'Skipped %d legacy pages (news and FAQ are no longer part of this site): %s.',
                count($this->skippedKinds),
                implode(', ', $this->skippedKinds),
            ));
        }

        return self::SUCCESS;
    }

    private function connect(string $path): void
    {
        config([
            'database.connections.legacy' => [
                'driver' => 'sqlite',
                'database' => $path,
                'prefix' => '',
                'foreign_key_constraints' => false,
            ],
        ]);

        DB::purge('legacy');

        // Fail here rather than three steps later with a confusing error.
        DB::connection('legacy')->getPdo();
    }

    private function clear(): void
    {
        DB::table('page_images')->delete();
        DB::table('page_translations')->delete();
        DB::table('pages')->delete();
        DB::table('navigation_item_translations')->delete();
        DB::table('navigation_items')->delete();
        DB::table('redirects')->delete();
        DB::table('images')->delete();
        DB::table('settings')->delete();

        $this->components->info('Existing content cleared.');
    }

    private function importLanguages(): void
    {
        if (DB::table('languages')->exists()) {
            $this->components->info('Languages already present, left untouched.');

            return;
        }

        $native = ['en' => 'English', 'my' => 'မြန်မာ', 'ru' => 'Русский'];

        foreach (['en', 'my', 'ru'] as $index => $code) {
            Language::create([
                'code' => $code,
                'name' => Str::headline($code === 'my' ? 'Myanmar' : ($code === 'ru' ? 'Russian' : 'English')),
                'native_name' => $native[$code] ?? strtoupper($code),
                'lang_country' => $code,
                'direction' => 'ltr',
                'is_default' => $code === 'en',
                'is_active' => true,
                'sort_order' => $index,
            ]);
        }

        $this->components->info('Languages created: en, my, ru.');
    }

    private function importSettings(): void
    {
        $known = [
            'company.name' => 'company',
            'company.tagline' => 'company',
            'company.email' => 'company',
            'company.phone' => 'company',
            'company.phone_secondary' => 'company',
            'company.address' => 'company',
            'company.map_query' => 'company',
            'company.office_hours' => 'company',
            'theme.primary' => 'theme',
            'theme.accent' => 'theme',
            'theme.surface' => 'theme',
            'theme.text' => 'theme',
            'theme.animation' => 'theme',
            'theme.animation_duration' => 'theme',
        ];

        $imported = 0;

        foreach ($this->legacy('settings') as $row) {
            $key = (string) $row->key;

            if (! isset($known[$key]) || ! filled($row->value)) {
                continue;
            }

            Setting::put($key, (string) $row->value, $known[$key]);

            $imported++;
        }

        // Defaults the new site reads but the old database never stored.
        $defaults = [
            'company.tagline' => 'Russian agricultural biotechnology for Myanmar farms.',
            'company.office_hours' => 'Monday – Saturday, 9:00 – 18:00',
            'contact.form_enabled' => true,
            'contact.success_message' => 'Thank you for your message. Our team will get back to you shortly.',
        ];

        foreach ($defaults as $key => $value) {
            if (Setting::get($key) === null) {
                Setting::put($key, $value, Str::before($key, '.'));
            }
        }

        Setting::put(
            'contact.notify_email',
            (string) (Setting::get('company.email') ?? ''),
            'contact',
        );

        $this->components->info("Settings imported: {$imported}.");
    }

    private function importImages(): void
    {
        $pagesByLegacyId = $this->legacy('pages')->keyBy('id');

        foreach ($this->legacy('images') as $row) {
            $legacyId = (int) $row->id;
            $path = trim((string) $row->path);

            if ($path === '') {
                continue;
            }

            $image = Image::create([
                'disk' => (string) ($row->disk ?: 'public'),
                'path' => $path,
                'original_name' => $this->fileName($path),
                'alt' => filled($row->alt) ? $this->clean((string) $row->alt, self::LOCALES[0]) : null,
            ]);

            $this->imageMap[$legacyId] = $image->getKey();

            $legacyPageId = (int) $row->imageable_id;

            if (($row->imageable_type ?? null) !== 'App\\Models\\Page' || ! $pagesByLegacyId->has($legacyPageId)) {
                continue;
            }

            if ($row->role === 'featured') {
                $this->featuredByPage[$legacyPageId] = $image->getKey();
            } else {
                $this->galleryByPage[$legacyPageId][] = $image->getKey();
            }
        }

        $this->components->info('Media imported: '.count($this->imageMap).'.');
    }

    private function importPages(): void
    {
        $seo = $this->legacy('seo')
            ->where('model_type', 'App\\Models\\Page')
            ->keyBy('model_id');

        $blocksByPage = $this->legacyBlocks();

        foreach ($this->legacy('pages')->sortBy([['sort_order', 'asc'], ['id', 'asc']]) as $row) {
            $legacyId = (int) $row->id;

            if (in_array($this->kind((string) $row->kind), self::SKIPPED_KINDS, true)) {
                $this->skippedKinds[] = (string) $row->path;

                continue;
            }

            $page = Page::create([
                'kind' => $this->kind((string) $row->kind),
                'slug' => $this->slug($row),
                'status' => (bool) $row->is_active ? Page::STATUS_PUBLISHED : Page::STATUS_DRAFT,
                'featured_image_id' => $this->featuredByPage[$legacyId] ?? null,
                'animation' => filled($row->animation) ? (string) $row->animation : null,
                'sort_order' => (int) ($row->sort_order ?? 0),
                'published_at' => $row->published_at ?: ($row->updated_at ?: now()),
            ]);

            $this->pageMap[$legacyId] = $page;

            foreach (self::LOCALES as $locale) {
                $this->importTranslation($page, $row, $blocksByPage[$legacyId] ?? [], $seo->get($legacyId), $locale);
            }

            $gallery = $this->galleryByPage[$legacyId] ?? [];

            foreach ($gallery as $index => $imageId) {
                $page->galleryImages()->attach($imageId, ['sort_order' => $index]);
            }
        }

        $this->components->info('Pages imported: '.count($this->pageMap).'.');
    }

    /**
     * Listing pages for the content kinds that have entries.
     *
     * An archive is a normal page: the editor can retitle it, restyle it or
     * delete it. It only exists so a kind that carries entries is reachable
     * from the menu instead of answering 404 — the blocks do the listing, and
     * they read the library at render time, so new entries appear by
     * themselves. Only the kinds this site still publishes are listed; news
     * and FAQ entries are skipped by importPages, so they need no archive.
     */
    private function importArchives(): void
    {
        // Listing pages: one address that gathers everything of a kind. They
        // are ordinary pages carrying a collection block, so an editor can
        // reword them or add sections without any code change.
        $archives = [
            [
                'kind' => Page::KIND_PRODUCT,
                'slug' => 'products',
                'label' => 'ui.nav.products',
                'block' => 'product_collection',
                'data' => ['source' => Page::KIND_PRODUCT, 'limit' => 12],
            ],
            [
                'kind' => Page::KIND_PROJECT,
                'slug' => 'our-next-projects',
                'label' => 'ui.nav.next_projects',
                'block' => 'post_collection',
                'data' => ['source' => Page::KIND_PROJECT, 'limit' => 12],
            ],
        ];

        foreach ($archives as $archive) {
            $exists = Page::query()
                ->withTrashed()
                ->where('slug', $archive['slug'])
                ->exists();

            $hasEntries = Page::query()
                ->where('kind', $archive['kind'])
                ->exists();

            if ($exists || ! $hasEntries) {
                continue;
            }

            $page = Page::create([
                'kind' => Page::KIND_PAGE,
                'slug' => $archive['slug'],
                'status' => Page::STATUS_PUBLISHED,
                'published_at' => now(),
            ]);

            foreach (self::LOCALES as $locale) {
                $label = trans($archive['label'], [], $locale);

                $page->translations()->create([
                    'locale' => $locale,
                    'title' => $label,
                    'content_blocks' => [
                        ['type' => 'section', 'data' => [
                            'layout' => 'banner',
                            'fields' => [['type' => 'title', 'value' => $label]],
                        ]],
                        ['type' => $archive['block'], 'data' => $archive['data']],
                    ],
                ]);
            }

            // Registered so a menu URL for the archive resolves to it below.
            $this->pageMap[-$page->getKey()] = $page;

            $this->components->info("Archive page created for {$archive['kind']} at /{$archive['slug']}.");
        }
    }

    /**
     * @param  array<int, object>  $blocks
     */
    private function importTranslation(Page $page, object $row, array $blocks, ?object $seo, string $locale): void
    {
        $suffix = $locale === 'en' ? '' : '_'.$locale;
        $isDefault = $locale === self::LOCALES[0];

        $title = $this->clean(trim((string) ($row->{'title'.$suffix} ?? '')), $locale);
        $excerpt = $this->clean(trim((string) ($row->{'excerpt'.$suffix} ?? '')), $locale);
        $description = $this->clean(trim((string) ($row->{'meta_description'.$suffix} ?? '')), $locale);
        $content = $this->clean(trim((string) ($row->{'content'.$suffix} ?? '')), $locale);

        // A locale row exists only where something was actually written in
        // that language. The old site often left `title_my` empty while the
        // blocks carried `data_my`, so translated block payloads count too.
        $hasLocalisedBlocks = collect($blocks)->contains(
            fn (object $block): bool => trim((string) ($block->{'data'.$suffix} ?? '')) !== '',
        );

        if (! $isDefault && ! $hasLocalisedBlocks
            && $title === '' && $excerpt === '' && $description === '' && $content === '') {
            return;
        }

        $payload = [];

        foreach ($blocks as $block) {
            $field = 'data'.$suffix;
            $json = trim((string) ($block->{$field} ?? '')) !== ''
                ? (string) $block->{$field}
                : (string) $block->data;

            $json = $this->clean($json, $locale);
            $data = self::cleanDeep(json_decode($json, true), $locale);
            $payload[] = [
                'type' => (string) $block->type,
                'data' => is_array($data) ? $data : [],
            ];
        }

        // The old pages also had one free-text body. It becomes a plain
        // section unless a block already carries rich text.
        $hasRichText = collect($payload)->contains(
            fn (array $block): bool => collect($block['data']['fields'] ?? [])->contains('type', 'rich_text'),
        );

        if ($content !== '' && ! $hasRichText) {
            $body = [
                'type' => 'section',
                'data' => [
                    'layout' => 'plain',
                    'fields' => [
                        ['type' => 'rich_text', 'value' => $content],
                    ],
                ],
            ];

            // The body used to be pushed in front of everything, which put a
            // loose paragraph above a page banner: on the live site it read as
            // stray text before the hero. When a banner opens the page, the
            // body follows it instead.
            $opensWithBanner = ($payload[0]['data']['layout'] ?? null) === 'banner'
                || ($payload[0]['type'] ?? null) === 'banner';

            if ($opensWithBanner) {
                array_splice($payload, 1, 0, [$body]);
            } else {
                array_unshift($payload, $body);
            }
        }

        // The old `seo` table held a single, English, row per page, so its
        // title and description must not leak into the other languages. A
        // missing translated title is stored as null on purpose: the page
        // still needs its row (its blocks are translated), and the model falls
        // back to the default language at render time.
        PageTranslation::create([
            'page_id' => $page->getKey(),
            'locale' => $locale,
            'title' => $title !== ''
                ? $title
                : ($isDefault ? (Str::headline($page->slug)) : null),
            'excerpt' => $excerpt !== '' ? $excerpt : null,
            'content_blocks' => $payload,
            'seo' => array_filter([
                'title' => $isDefault && filled($seo?->title) ? (string) $seo->title : null,
                'description' => $description !== ''
                    ? $description
                    : ($excerpt !== '' ? $excerpt : ($isDefault && filled($seo?->description) ? (string) $seo->description : null)),
                'robots' => filled($seo?->robots) ? (string) $seo->robots : null,
            ]),
        ]);
    }

    /**
     * Legacy blocks, grouped by the page they belong to.
     *
     * @return array<int, array<int, object>>
     */
    private function legacyBlocks(): array
    {
        $grouped = [];

        $rows = DB::connection('legacy')
            ->table('block_page')
            ->join('blocks', 'blocks.id', '=', 'block_page.block_id')
            ->orderBy('block_page.page_id')
            ->orderBy('block_page.sort_order')
            ->orderBy('blocks.id')
            ->select('blocks.*', 'block_page.page_id as pivot_page_id')
            ->get();

        foreach ($rows as $row) {
            $grouped[(int) $row->pivot_page_id][] = $row;
        }

        return $grouped;
    }

    private function importNavigation(): void
    {
        $items = $this->legacy('menu_items')->sortBy('sort_order');
        $map = [];

        foreach ($items as $row) {
            $url = trim((string) $row->url);
            $page = $this->pageForUrl($url);

            // A menu entry that points at a page nobody created is a dead
            // link. The row is kept so the editor sees it in the admin, but
            // it stays switched off until a page or a real URL is chosen.
            $reachable = $page !== null
                || $url === ''
                || str_starts_with($url, '#')
                || preg_match('#^https?://#i', $url) === 1;

            if (! $reachable) {
                $this->unreachableLinks[] = $url;
            }

            $item = NavigationItem::create([
                'parent_id' => null,
                'placement' => NavigationItem::PLACEMENT_HEADER,
                'target_type' => $page !== null ? NavigationItem::TARGET_PAGE : NavigationItem::TARGET_URL,
                'page_id' => $page?->getKey(),
                // A parent that only groups children has no destination; '#'
                // keeps it from looking like a broken link in the admin.
                'url' => $page !== null ? null : ($url !== '' ? $url : '#'),
                'sort_order' => (int) $row->sort_order,
                'is_active' => (bool) $row->is_active && $reachable,
                'opens_new_tab' => ($row->target ?? '_self') === '_blank',
            ]);

            $map[(int) $row->id] = $item;

            foreach (self::LOCALES as $locale) {
                $label = $this->clean(trim((string) ($row->{'label'.($locale === 'en' ? '' : '_'.$locale)} ?? '')), $locale);

                if ($label === '') {
                    continue;
                }

                $item->translations()->create(['locale' => $locale, 'label' => $label]);
            }
        }

        // Second pass so a child never points at a parent that was not created.
        foreach ($items as $row) {
            $parentId = $row->parent_id ? (int) $row->parent_id : 0;

            if ($parentId === 0 || ! isset($map[$parentId], $map[(int) $row->id])) {
                continue;
            }

            $map[(int) $row->id]->forceFill(['parent_id' => $map[$parentId]->getKey()])->save();
        }

        $this->importEssentialLinks();

        // The footer gets its own, independently editable menu; the seeder is
        // idempotent, so importing twice never doubles it.
        $footerLinks = FooterNavigationSeeder::seed();

        if ($footerLinks > 0) {
            $this->components->info("Footer menu seeded with {$footerLinks} links.");
        }

        $this->components->info('Navigation imported: '.count($map).'.');

        if ($this->unreachableLinks !== []) {
            $this->components->warn(
                'Switched off navigation entries with no page at their address: '
                .implode(', ', array_unique($this->unreachableLinks))
                .'. Create the page or point the entry somewhere real to publish it.'
            );
        }
    }

    /**
     * Header links the old menu never had, but the new site needs.
     *
     * Contact was only reachable from the footer, so it is added here, at the
     * end of the menu, and only when the page it needs is actually there.
     */
    private function importEssentialLinks(): void
    {
        $links = [
            ['path' => '/contact', 'label' => 'ui.nav.contact'],
        ];

        $position = (int) NavigationItem::query()
            ->where('placement', NavigationItem::PLACEMENT_HEADER)
            ->max('sort_order');

        foreach ($links as $link) {
            $page = $this->pageForUrl($link['path']);

            if ($page === null) {
                continue;
            }

            $alreadyLinked = NavigationItem::query()
                ->where('placement', NavigationItem::PLACEMENT_HEADER)
                ->where('page_id', $page->getKey())
                ->exists();

            if ($alreadyLinked) {
                continue;
            }

            $item = NavigationItem::create([
                'parent_id' => null,
                'placement' => NavigationItem::PLACEMENT_HEADER,
                'target_type' => NavigationItem::TARGET_PAGE,
                'page_id' => $page->getKey(),
                'sort_order' => ++$position,
                'is_active' => true,
                'opens_new_tab' => false,
            ]);

            foreach (self::LOCALES as $locale) {
                $item->translations()->create([
                    'locale' => $locale,
                    'label' => trans($link['label'], [], $locale),
                ]);
            }

            $this->components->info("Header link added for {$link['path']}.");
        }
    }

    /**
     * Addresses the old site published that the new one does not have.
     *
     * `/blog` was in the main menu and never held a single post. It used to be
     * pointed at the news listing; that section is gone, so it now goes to the
     * front door rather than a 404. Rows live in the `redirects` table, which is
     * editable in the admin.
     */
    private function importRedirects(): void
    {
        $moved = [
            'blog' => '/',
            'about-us' => '/about',
        ];

        foreach ($moved as $from => $to) {
            if (Page::pathExists('/'.$from)) {
                continue;
            }

            Redirect::query()->updateOrCreate(
                ['from_path' => $from],
                [
                    'to_path' => $to,
                    'status_code' => Redirect::STATUS_MOVED_PERMANENTLY,
                    'is_active' => true,
                ],
            );

            $this->components->info("/{$from} now redirects to {$to}.");
        }
    }

    /**
     * Put the office map on the contact page.
     *
     * The legacy site rendered its map from a template, not from a block, so
     * nothing carried over and /contact had no map at all. The coordinates in
     * the site settings are enough: the block builds its own embed.
     */
    private function importContactMap(): void
    {
        $contact = $this->pageForUrl('/contact');

        if ($contact === null) {
            return;
        }

        foreach ($contact->translations as $translation) {
            $blocks = $translation->blocks();

            $hasMap = collect($blocks)->contains(
                fn (array $block): bool => ($block['type'] ?? null) === 'map',
            );

            if ($hasMap) {
                continue;
            }

            $blocks[] = ['type' => 'map', 'data' => []];

            $translation->forceFill(['content_blocks' => $blocks])->save();
        }

        $this->components->info('Map added to the contact page.');
    }

    private function pageForUrl(string $url): ?Page
    {
        if ($url === '') {
            return null;
        }

        $path = '/'.ltrim($url, '/');

        return collect($this->pageMap)->first(
            fn (Page $page): bool => $page->path() === $path,
        );
    }

    /**
     * Repairs character-level mistakes in the legacy Burmese copy.
     *
     * The old editor occasionally typed the Khmer coeng (U+17D2) where the
     * Myanmar virama (U+1039) belongs, which renders as a broken glyph. The
     * two characters are visually identical in some fonts, so this is a
     * recurring typo rather than a one-off.
     */
    private function clean(string $value, string $locale): string
    {
        $value = $this->validUtf8($value);

        return $locale === 'my'
            ? str_replace("\u{17D2}", "\u{1039}", $value)
            : $value;
    }

    /**
     * Legacy rows can hold bytes that are not valid UTF-8, which breaks JSON
     * encoding further down the stack: the admin serialises a record into a
     * Livewire snapshot, and one bad byte turns that screen into a 500. The
     * bytes are usually Windows-1252 punctuation from the old Windows machine.
     */
    private function validUtf8(string $value): string
    {
        return (string) Text::utf8($value);
    }

    /**
     * The file name of a media path.
     *
     * `basename()` is locale aware and replaces the bytes of a multi-byte
     * character with underscores — it turned `Top–quality.jpg` into a name
     * that was no longer valid UTF-8. Splitting on the separator keeps the
     * character intact.
     */
    private function fileName(string $path): string
    {
        return Text::fileName($path) ?: 'image';
    }

    /**
     * The same repair for a decoded block payload.
     *
     * Legacy JSON escaped the character as `\u17d2`, so the raw string never
     * matched; the decoded tree is the only place it can be found reliably.
     */
    private static function cleanDeep(mixed $value, string $locale): mixed
    {
        if (is_string($value)) {
            if (! mb_check_encoding($value, 'UTF-8')) {
                $value = (string) iconv('UTF-8', 'UTF-8//IGNORE', $value);
            }

            return $locale === 'my' ? str_replace("\u{17D2}", "\u{1039}", $value) : $value;
        }

        if (is_array($value)) {
            return array_map(fn (mixed $item): mixed => self::cleanDeep($item, $locale), $value);
        }

        return $value;
    }

    /** The new slug, taken from the old path so the public URL survives. */
    private function slug(object $row): string
    {
        $segments = array_values(array_filter(explode('/', trim((string) $row->path, '/'))));

        $slug = $segments === [] ? (string) $row->slug : (string) end($segments);
        $slug = Str::slug($slug) ?: Str::slug((string) $row->slug) ?: 'page-'.$row->id;

        $kind = $this->kind((string) $row->kind);
        $candidate = $slug;
        $suffix = 2;

        while (Page::withTrashed()->where('kind', $kind)->where('slug', $candidate)->exists()) {
            $candidate = $slug.'-'.$suffix++;
        }

        return $candidate;
    }

    private function kind(string $kind): string
    {
        return in_array($kind, [
            Page::KIND_PRODUCT,
            Page::KIND_NEWS,
            Page::KIND_FAQ,
            Page::KIND_FEEDBACK,
            Page::KIND_PROJECT,
        ], true) ? $kind : Page::KIND_PAGE;
    }

    /**
     * @return Collection<int, object>
     */
    private function legacy(string $table): Collection
    {
        return DB::connection('legacy')->table($table)->get();
    }
}
