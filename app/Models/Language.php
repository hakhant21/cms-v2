<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Schema;

/**
 * A locale the site can render. This table decides the default language and
 * the switcher's options, so no locale list is hard-coded anywhere else.
 */
class Language extends Model
{
    /** @var list<string> */
    protected $fillable = [
        'code',
        'name',
        'native_name',
        'lang_country',
        'direction',
        'is_default',
        'is_active',
        'sort_order',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_default' => 'boolean',
            'is_active' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    private static ?string $defaultCode = null;

    /** @var list<string>|null */
    private static ?array $activeCodes = null;

    protected static function booted(): void
    {
        // Exactly one locale can be the fallback; setting a new default clears
        // the previous one so content resolution is never ambiguous.
        static::saved(function (self $language): void {
            if ($language->is_default) {
                static::query()
                    ->whereKeyNot($language->getKey())
                    ->where('is_default', true)
                    ->update(['is_default' => false]);
            }

            self::forgetCache();
        });

        static::deleted(function (self $language): void {
            self::forgetCache();

            if (static::query()->where('is_default', true)->exists()) {
                return;
            }

            $fallback = static::query()->orderBy('sort_order')->first();
            $fallback?->forceFill(['is_default' => true])->save();
        });
    }

    public static function forgetCache(): void
    {
        self::$defaultCode = null;
        self::$activeCodes = null;
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true)->orderBy('sort_order');
    }

    /** The locale used whenever a translation is missing. */
    public static function defaultCode(): string
    {
        if (self::$defaultCode !== null) {
            return self::$defaultCode;
        }

        if (! Schema::hasTable('languages')) {
            return self::$defaultCode = (string) config('app.locale', 'en');
        }

        return self::$defaultCode = (string) (
            static::query()->where('is_default', true)->value('code')
            ?? static::query()->orderBy('sort_order')->value('code')
            ?? config('app.locale', 'en')
        );
    }

    /**
     * @return list<string>
     */
    public static function activeCodes(): array
    {
        if (self::$activeCodes !== null) {
            return self::$activeCodes;
        }

        if (! Schema::hasTable('languages')) {
            return self::$activeCodes = [(string) config('app.locale', 'en')];
        }

        $codes = static::query()->active()->pluck('code')->all();

        return self::$activeCodes = $codes === [] ? [(string) config('app.locale', 'en')] : array_values($codes);
    }
}
