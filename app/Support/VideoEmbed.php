<?php

namespace App\Support;

/**
 * Turns a pasted video link into something an `<iframe>` can play.
 *
 * Editors copy whatever their browser shows them — a `youtu.be` short link, a
 * watch page with a timestamp, a Vimeo page — while an embed frame only accepts
 * a player URL. Doing that translation here keeps the Blade views to a single
 * `src` attribute and means one place to support a new provider.
 */
final class VideoEmbed
{
    /**
     * The player URL for a pasted link, or `null` when the link is not a video
     * that can be embedded (an unparseable string, or a provider we do not know).
     */
    public static function url(?string $url): ?string
    {
        $id = self::youtubeId($url);

        if ($id !== null) {
            return "https://www.youtube-nocookie.com/embed/{$id}";
        }

        $vimeo = self::vimeoId($url);

        if ($vimeo !== null) {
            return "https://player.vimeo.com/video/{$vimeo}";
        }

        return null;
    }

    /**
     * A still image to show before playback, when the provider offers one.
     *
     * YouTube exposes a predictable thumbnail address per video id; anything
     * else is left to the template's gradient placeholder.
     */
    public static function thumbnail(?string $url): ?string
    {
        $id = self::youtubeId($url);

        return $id === null ? null : "https://i.ytimg.com/vi/{$id}/hqdefault.jpg";
    }

    /** Which service a link points at, for labelling and markup. */
    public static function provider(?string $url): ?string
    {
        if (self::youtubeId($url) !== null) {
            return 'youtube';
        }

        return self::vimeoId($url) !== null ? 'vimeo' : null;
    }

    public static function isEmbeddable(?string $url): bool
    {
        return self::url($url) !== null;
    }

    /**
     * The eleven-character video id of any YouTube address shape.
     *
     * Handled: `watch?v=`, `youtu.be/`, `/embed/`, `/shorts/`, `/live/`, and an
     * id on its own. The `v` parameter is read from the query string first
     * because a watch URL also carries other parameters, while the path shapes
     * put the id straight after a known segment.
     */
    private static function youtubeId(?string $url): ?string
    {
        $url = trim((string) $url);

        if ($url === '') {
            return null;
        }

        // A bare id, as a CMS import sometimes stores it.
        if (preg_match('/^[A-Za-z0-9_-]{11}$/', $url) === 1) {
            return $url;
        }

        $host = strtolower((string) parse_url($url, PHP_URL_HOST));

        if (preg_match('/(^|\.)(youtube\.com|youtube-nocookie\.com)$/', $host) === 1) {
            $query = (string) parse_url($url, PHP_URL_QUERY);
            parse_str($query, $params);

            if (isset($params['v']) && is_string($params['v']) && preg_match('/^[A-Za-z0-9_-]{11}$/', $params['v']) === 1) {
                return $params['v'];
            }

            $path = (string) parse_url($url, PHP_URL_PATH);

            if (preg_match('#/(?:embed|shorts|live|v)/([A-Za-z0-9_-]{11})#', $path, $matches) === 1) {
                return $matches[1];
            }

            return null;
        }

        if ($host === 'youtu.be') {
            $path = ltrim((string) parse_url($url, PHP_URL_PATH), '/');

            return preg_match('/^([A-Za-z0-9_-]{11})/', $path, $matches) === 1 ? $matches[1] : null;
        }

        return null;
    }

    /** The numeric id of a Vimeo address, whether page or player. */
    private static function vimeoId(?string $url): ?string
    {
        $host = strtolower((string) parse_url(trim((string) $url), PHP_URL_HOST));

        if (preg_match('/(^|\.)(vimeo\.com|player\.vimeo\.com)$/', $host) !== 1) {
            return null;
        }

        $path = (string) parse_url((string) $url, PHP_URL_PATH);

        return preg_match('#(?:^|/)(?:video/)?(\d+)#', $path, $matches) === 1 ? $matches[1] : null;
    }
}
