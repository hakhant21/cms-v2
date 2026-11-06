<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** The visible label of a navigation item in one locale. */
class NavigationItemTranslation extends Model
{
    /** @var list<string> */
    protected $fillable = ['navigation_item_id', 'locale', 'label', 'is_manual'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_manual' => 'boolean',
        ];
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(NavigationItem::class, 'navigation_item_id');
    }
}
