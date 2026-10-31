<?php

namespace App\Support;

use App\Models\Language;

/**
 * Builds public URLs for the current (or a given) locale.
 *
 * The default language lives at the root so its URLs stay short and stable,
 * every other language is prefixed with its code. Nothing in the application
 * hard-codes a locale, the prefix always follows the languages table.
 */
final class LocaleUrl
{
    /** Prefix a site path such as `/about` for the current language. */
    public static function path(?string $path, ?string $locale = null): string
    {
        $path = '/'.ltrim((string) ($path ?: '/'), '/');
        $locale ??= app()->getLocale();

        if ($locale === Language::defaultCode()) {
            return $path;
        }

        return $path === '/' ? '/'.$locale : '/'.$locale.$path;
    }

    /** The absolute URL of a site path in another language. */
    public static function forLocale(string $path, string $locale): string
    {
        return url(self::path($path, $locale));
    }

    /**
     * Remove a locale prefix from a request path, so it can be translated to
     * another language without doubling the prefix.
     */
    public static function strip(string $path): string
    {
        $segments = explode('/', trim($path, '/'));

        if ($segments !== [] && in_array($segments[0], Language::activeCodes(), true)) {
            array_shift($segments);
        }

        return '/'.implode('/', $segments);
    }
}
