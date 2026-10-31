<?php

use App\Models\Setting;
use App\Support\LocaleUrl;
use Illuminate\Support\Facades\Storage;

/**
 * Small view helpers.
 *
 * Blade templates only need two things from the outside world: the current
 * language's version of a path, and a typed setting. Everything else comes
 * through the layout composer.
 */
if (! function_exists('locale_path')) {
    /** The link target for a site path such as `/about` in the active language. */
    function locale_path(?string $path = '/'): string
    {
        return LocaleUrl::path($path);
    }
}

if (! function_exists('setting')) {
    /** A value from the settings screen, with a sensible fallback. */
    function setting(string $key, ?string $default = null): ?string
    {
        return Setting::get($key, $default);
    }
}

if (! function_exists('setting_bool')) {
    function setting_bool(string $key, bool $default = false): bool
    {
        return Setting::bool($key, $default);
    }
}

if (! function_exists('media_url')) {
    /**
     * Public URL for a stored media path. Absolute URLs and root paths are
     * returned untouched so old content keeps working after the migration.
     */
    function media_url(?string $path): ?string
    {
        if ($path === null || trim($path) === '') {
            return null;
        }

        $path = trim($path);

        if (str_starts_with($path, 'http://') || str_starts_with($path, 'https://') || str_starts_with($path, '/')) {
            return $path;
        }

        return Storage::disk('public')->url($path);
    }
}

if (! function_exists('rich_text')) {
    /**
     * Editor HTML, limited to the tags the design system styles. Anything else
     * is stripped, so a pasted snippet can never inject a script.
     */
    function rich_text(?string $html): string
    {
        if ($html === null || trim($html) === '') {
            return '';
        }

        $allowed = '<p><br><strong><b><em><i><ul><ol><li><a><h2><h3><h4><blockquote><span>';
        $clean = strip_tags($html, $allowed);

        // Drop event handlers and javascript: URLs that survive tag stripping.
        $clean = preg_replace('/\son[a-z]+\s*=\s*("[^"]*"|\'[^\']*\'|[^\s>]+)/i', '', $clean) ?? $clean;

        return preg_replace('/(href|src)\s*=\s*(["\'])\s*javascript:[^"\']*\2/i', '', $clean) ?? $clean;
    }
}
