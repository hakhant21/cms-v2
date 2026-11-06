<?php

namespace App\Models;

use App\Support\LocaleUrl;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

/**
 * The locale-neutral shell of a page. Everything a visitor reads lives in
 * `page_translations`, one row per locale, so a new language never needs a
 * migration and a missing translation falls back to the default language.
 */
class Page extends Model
{
    use SoftDeletes;

    public const KIND_PAGE = 'page';

    public const KIND_PRODUCT = 'product';

    public const KIND_NEWS = 'news';

    public const KIND_FAQ = 'faq';

    public const KIND_FEEDBACK = 'feedback';

    public const KIND_PROJECT = 'next-project';

    public const STATUS_DRAFT = 'draft';

    public const STATUS_PUBLISHED = 'published';

    public const STATUS_ARCHIVED = 'archived';

    /**
     * Mirrors the column defaults so a new page is a draft of kind `page`
     * without waiting for a reload.
     *
     * @var array<string, mixed>
     */
    protected $attributes = [
        'kind' => self::KIND_PAGE,
        'status' => self::STATUS_DRAFT,
        'sort_order' => 0,
        'auto_translate' => true,
    ];

    /** @var list<string> */
    protected $fillable = [
        'kind',
        'slug',
        'status',
        'featured_image_id',
        'animation',
        'sort_order',
        'published_at',
        'auto_translate',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'published_at' => 'datetime',
            'sort_order' => 'integer',
            'auto_translate' => 'boolean',
        ];
    }

    /**
     * Resolved translations for this instance, keyed by locale, so a page that
     * renders several blocks does not re-read the relation each time.
     *
     * @var array<string, PageTranslation|null>
     */
    private array $resolvedTranslations = [];

    public function translations(): HasMany
    {
        return $this->hasMany(PageTranslation::class);
    }

    public function featuredImage(): BelongsTo
    {
        return $this->belongsTo(Image::class, 'featured_image_id');
    }

    public function galleryImages(): BelongsToMany
    {
        return $this->belongsToMany(Image::class, 'page_images')
            ->withPivot('sort_order')
            ->withTimestamps()
            ->orderByPivot('sort_order');
    }

    /**
     * Keep the gallery in the order the editor arranged it.
     *
     * Filament's multiple-select syncs the rows but leaves every pivot at its
     * default `sort_order` of 0, so the photos would fall back to whatever order
     * the join happened to return. Numbering them here makes the first photo
     * the one that opens the product page.
     *
     * @param  array<int, int|string>  $imageIds  ids in the chosen order
     */
    public function reorderGallery(array $imageIds): void
    {
        foreach (array_values($imageIds) as $position => $id) {
            $this->galleryImages()->updateExistingPivot((int) $id, ['sort_order' => $position]);
        }

        // The relation was loaded before the pivot rows changed.
        $this->unsetRelation('galleryImages');
    }

    public function scopePublished(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_PUBLISHED);
    }

    public function scopeOfKind(Builder $query, string $kind): Builder
    {
        return $query->where('kind', $kind);
    }

    public function scopeOrdered(Builder $query): Builder
    {
        return $query->orderBy('sort_order')->orderBy('id');
    }

    /**
     * The copy to render: the requested locale, else the site default, else
     * whatever exists. Returns null for a page nobody has written yet.
     */
    public function translation(?string $locale = null): ?PageTranslation
    {
        $locale ??= app()->getLocale();

        if (array_key_exists($locale, $this->resolvedTranslations)) {
            return $this->resolvedTranslations[$locale];
        }

        $translations = $this->relationLoaded('translations')
            ? $this->translations
            : $this->translations()->get();

        return $this->resolvedTranslations[$locale] = $translations->firstWhere('locale', $locale)
            ?? $translations->firstWhere('locale', Language::defaultCode())
            ?? $translations->first();
    }

    public function localizedTitle(?string $locale = null): string
    {
        $locale ??= app()->getLocale();

        $title = $this->translation($locale)?->title;

        // A translation row can exist for its blocks while its own title is
        // still unwritten, in which case the default language is the best
        // answer available.
        if (! filled($title) && $locale !== Language::defaultCode()) {
            $title = $this->translation(Language::defaultCode())?->title;
        }

        return filled($title) ? (string) $title : Str::headline($this->slug);
    }

    public function localizedExcerpt(?string $locale = null): ?string
    {
        return $this->translation($locale)?->excerpt;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function localizedContentBlocks(?string $locale = null): array
    {
        return $this->translation($locale)?->blocks() ?? [];
    }

    /**
     * Flattened metadata for the `<x-seo>` head, with safe fallbacks.
     *
     * @return array<string, string|null>
     */
    public function seoMeta(?string $locale = null): array
    {
        $translation = $this->translation($locale);
        $seo = is_array($translation?->seo) ? $translation->seo : [];

        return [
            'title' => $seo['title'] ?? $this->localizedTitle($locale).' | '.config('app.name'),
            'description' => $seo['description'] ?? $translation?->excerpt,
            'image' => $seo['og_image'] ?? $this->featuredImage?->url(),
            'canonical' => $seo['canonical'] ?? url()->current(),
            'type' => $seo['type'] ?? 'website',
            'site_name' => (string) config('app.name'),
            'robots' => $seo['robots'] ?? 'index,follow',
        ];
    }

    /** The public path for this page, derived from its kind so it cannot drift. */
    public function path(): string
    {
        return match ($this->kind) {
            self::KIND_PRODUCT => '/products/'.$this->slug,
            self::KIND_NEWS => '/news/'.$this->slug,
            default => '/'.$this->slug,
        };
    }

    /**
     * The section a page sits under, used for the breadcrumb trail.
     *
     * @return array{0: string, 1: string}|null Label and path, or null at the top.
     */
    public function section(): ?array
    {
        return match ($this->kind) {
            self::KIND_PRODUCT => ['ui.nav.products', '/products'],
            self::KIND_NEWS => ['ui.nav.news', '/news'],
            default => null,
        };
    }

    /**
     * JSON-LD for this page: its type, the trail that leads to it and what it
     * is about, built only from fields the editor already filled in.
     *
     * @return array<string, mixed>
     */
    public function structuredData(?string $locale = null): array
    {
        $locale ??= app()->getLocale();
        $url = url(LocaleUrl::path($this->path(), $locale));
        $title = $this->localizedTitle($locale);
        $description = $this->localizedExcerpt($locale);
        $image = $this->featuredImage?->url();

        $node = array_filter([
            '@type' => match ($this->kind) {
                self::KIND_NEWS => 'Article',
                self::KIND_PRODUCT => 'Product',
                default => 'WebPage',
            },
            '@id' => $url.'#page',
            'url' => $url,
            'name' => $title,
            'inLanguage' => $locale,
            'description' => $description,
            'image' => $image,
            'datePublished' => $this->published_at?->toAtomString(),
            'dateModified' => $this->updated_at?->toAtomString(),
            'isPartOf' => ['@id' => url('/').'#website'],
        ], fn (mixed $value): bool => $value !== null && $value !== '');

        // A product or an article is reached through its section, so a search
        // engine shows "Ruma > Products > Ruma Gold" instead of a bare URL.
        // The section is only added when a page actually lives there: a
        // breadcrumb that 404s is worse than a shorter trail.
        $crumbs = [['name' => __('ui.nav.home_page'), 'url' => url(LocaleUrl::path('/', $locale))]];

        if (($section = $this->section()) !== null && self::pathExists($section[1])) {
            $crumbs[] = ['name' => __($section[0]), 'url' => url(LocaleUrl::path($section[1], $locale))];
        }

        $crumbs[] = ['name' => $title, 'url' => $url];

        return [
            '@context' => 'https://schema.org',
            '@graph' => [
                [
                    '@type' => 'WebSite',
                    '@id' => url('/').'#website',
                    'url' => url('/'),
                    'name' => (string) config('app.name'),
                    'inLanguage' => $locale,
                ],
                $node,
                [
                    '@type' => 'BreadcrumbList',
                    'itemListElement' => array_map(
                        fn (array $crumb, int $index): array => [
                            '@type' => 'ListItem',
                            'position' => $index + 1,
                            'name' => $crumb['name'],
                            'item' => $crumb['url'],
                        ],
                        $crumbs,
                        array_keys($crumbs),
                    ),
                ],
            ],
        ];
    }

    /**
     * Whether a published page answers at this path. One indexed lookup on a
     * table with a handful of rows — cheaper than caching that could go stale.
     */
    public static function pathExists(string $path): bool
    {
        return self::query()
            ->published()
            ->where('slug', trim($path, '/'))
            ->where('kind', self::KIND_PAGE)
            ->exists();
    }
}
