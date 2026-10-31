<?php

namespace App\Support;

/**
 * String repairs that belong at the boundary.
 *
 * Legacy rows, browser uploads and CLI imports can all carry bytes that are
 * not valid UTF-8. Nothing downstream survives that — `json_encode()` refuses
 * the whole payload — which is how a single odd file name turned an admin
 * screen into a 500 instead of an edit form.
 */
final class Text
{
    /** The value as valid UTF-8, or null when there was nothing to repair. */
    public static function utf8(?string $value): ?string
    {
        if ($value === null || $value === '' || mb_check_encoding($value, 'UTF-8')) {
            return $value;
        }

        // The usual cause is Windows-1252 punctuation typed or uploaded on a
        // machine whose code page was not UTF-8.
        $converted = @mb_convert_encoding($value, 'UTF-8', 'Windows-1252');

        if (is_string($converted) && mb_check_encoding($converted, 'UTF-8')) {
            return $converted;
        }

        return (string) iconv('UTF-8', 'UTF-8//IGNORE', $value);
    }

    /**
     * The last segment of a path or URL, decoded.
     *
     * Neither `parse_url()` nor `basename()` can be used here: both replace the
     * bytes of a multi-byte character with underscores, so `Top–quality.jpg`
     * came back as a name that was no longer valid UTF-8. The path is taken
     * apart by hand instead.
     */
    public static function fileName(string $path): string
    {
        $path = (string) preg_replace('#[?\#].*$#', '', $path);
        $path = (string) preg_replace('#^[a-z][a-z0-9+.\-]*://[^/]*#i', '', $path);
        $segments = explode('/', rtrim($path, '/'));

        return (string) self::utf8(urldecode((string) end($segments)));
    }
}
