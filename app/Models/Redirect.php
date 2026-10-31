<?php

namespace App\Models;

use App\Support\LocaleUrl;
use Illuminate\Database\Eloquent\Model;

/**
 * One old address and where it points now.
 *
 * Paths are stored without a leading slash and without a language prefix, so
 * `/blog`, `/my/blog` and `/ru/blog` are all served by a single row.
 */
class Redirect extends Model
{
    public const STATUS_MOVED_PERMANENTLY = 301;

    public const STATUS_FOUND = 302;

    protected $fillable = [
        'from_path',
        'to_path',
        'status_code',
        'is_active',
        'hits',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status_code' => 'integer',
            'is_active' => 'boolean',
            'hits' => 'integer',
        ];
    }

    /** Strip the noise an editor pastes in: origin, leading slash, query. */
    public static function cleanPath(?string $path): string
    {
        $path = trim((string) $path);
        $path = (string) preg_replace('#^[a-z][a-z0-9+.\-]*://[^/]*#i', '', $path);
        $path = (string) preg_replace('#[?\#].*$#', '', $path);

        return trim($path, '/');
    }

    protected static function booted(): void
    {
        // Stored normalised so `blog`, `/blog/` and a pasted absolute URL are
        // all the same address, and the unique index actually means something.
        static::saving(function (self $redirect): void {
            $redirect->from_path = self::cleanPath($redirect->from_path);
            $redirect->to_path = trim((string) $redirect->to_path);

            if (! str_starts_with($redirect->to_path, '/')
                && preg_match('#^https?://#i', $redirect->to_path) !== 1) {
                $redirect->to_path = '/'.$redirect->to_path;
            }
        });
    }

    /**
     * Find the redirect that answers a request, ignoring the language prefix.
     */
    public static function forPath(string $path): ?self
    {
        $path = self::cleanPath($path);

        if ($path === '') {
            return null;
        }

        return static::query()
            ->where('from_path', $path)
            ->where('is_active', true)
            ->first();
    }

    /** The address this redirect resolves to, in the visitor's language. */
    public function target(string $locale): string
    {
        $to = (string) $this->to_path;

        if (preg_match('#^https?://#i', $to) === 1) {
            return $to;
        }

        return LocaleUrl::path($to, $locale);
    }
}
