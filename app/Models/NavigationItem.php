<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A header or footer link. The target is locale-neutral; only the label is
 * translated. Linking to a page instead of a raw URL means the menu follows
 * slug changes automatically.
 */
class NavigationItem extends Model
{
    public const PLACEMENT_HEADER = 'header';

    public const PLACEMENT_FOOTER = 'footer';

    public const TARGET_PAGE = 'page';

    public const TARGET_URL = 'url';

    /**
     * Mirrors the column defaults so a new link is an active header entry
     * without waiting for a reload.
     *
     * @var array<string, mixed>
     */
    protected $attributes = [
        'placement' => self::PLACEMENT_HEADER,
        'target_type' => self::TARGET_PAGE,
        'sort_order' => 0,
        'is_active' => true,
        'opens_new_tab' => false,
    ];

    /** @var list<string> */
    protected $fillable = [
        'parent_id',
        'placement',
        'target_type',
        'page_id',
        'url',
        'sort_order',
        'is_active',
        'opens_new_tab',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'opens_new_tab' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    /**
     * The child links a visitor may see. Switched-off children are filtered
     * here as well as at the top level, otherwise hiding a sub-link in the
     * admin had no effect on the site.
     */
    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id')
            ->active()
            ->orderBy('sort_order');
    }

    public function page(): BelongsTo
    {
        return $this->belongsTo(Page::class);
    }

    public function translations(): HasMany
    {
        return $this->hasMany(NavigationItemTranslation::class);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function scopePlacement(Builder $query, string $placement): Builder
    {
        return $query->where('placement', $placement);
    }

    public function href(): string
    {
        if ($this->target_type === self::TARGET_PAGE && $this->page !== null) {
            return $this->page->path();
        }

        return (string) $this->url;
    }

    public function localizedLabel(?string $locale = null): string
    {
        $locale ??= app()->getLocale();

        $labels = $this->relationLoaded('translations')
            ? $this->translations
            : $this->translations()->get();

        return $labels->firstWhere('locale', $locale)?->label
            ?? $labels->firstWhere('locale', Language::defaultCode())?->label
            ?? $labels->first()?->label
            ?? $this->page?->localizedTitle($locale)
            ?? (string) $this->url;
    }

    /**
     * Top-level links for a placement, with children and labels eager loaded.
     *
     * @return Collection<int, self>
     */
    public static function tree(string $placement = self::PLACEMENT_HEADER): Collection
    {
        return static::query()
            ->active()
            ->placement($placement)
            ->whereNull('parent_id')
            ->with(['translations', 'children.translations', 'children.page', 'page'])
            ->orderBy('sort_order')
            ->get();
    }
}
