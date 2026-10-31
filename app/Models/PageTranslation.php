<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** One locale's copy of a page: title, excerpt, sections and SEO. */
class PageTranslation extends Model
{
    /** @var list<string> */
    protected $fillable = ['page_id', 'locale', 'title', 'excerpt', 'content_blocks', 'seo'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'content_blocks' => 'array',
            'seo' => 'array',
        ];
    }

    public function page(): BelongsTo
    {
        return $this->belongsTo(Page::class);
    }

    /**
     * The section list with malformed entries stripped, so views can loop over
     * it without extra guards.
     *
     * @return array<int, array<string, mixed>>
     */
    public function blocks(): array
    {
        $blocks = is_array($this->content_blocks) ? $this->content_blocks : [];

        return array_values(array_filter($blocks, 'is_array'));
    }
}
