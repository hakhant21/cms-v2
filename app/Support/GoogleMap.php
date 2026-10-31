<?php

namespace App\Support;

use App\Models\Setting;

/**
 * The Google Map behind the Map block.
 *
 * Two addresses are involved, and which one is used matters:
 *
 * - When the site knows its Google listing (`company.map_place_id`, in the
 *   `0x…:0x…` form the share dialog hands out) the map is drawn with Google's
 *   own `maps/embed?pb=` address. That one carries the listing itself, so the
 *   embed shows the business — its name, address, opening hours and phone —
 *   instead of a bare pin, and the "Place info couldn't load" strip that the
 *   search form produces for a coordinate does not appear at all.
 * - Otherwise it falls back to the key-free `maps.google.com/maps?q=…` address,
 *   which needs no id and no billing account: a pin on the right spot, or a
 *   plain link, never a quota error.
 *
 * Either way the centre comes from the latitude and longitude in Settings, so
 * an editor moves the map by editing two numbers.
 */
final class GoogleMap
{
    public const DEFAULT_ZOOM = 15;

    /** Zoom is a Google level, so the range the tiles actually support. */
    public const MIN_ZOOM = 5;

    public const MAX_ZOOM = 20;

    /**
     * Metres covered by one screen pixel at zoom 0 on the equator. The ratio
     * halves with every zoom level and shrinks with the cosine of the latitude,
     * which is how the camera span below is derived from the zoom.
     */
    private const METRES_PER_PIXEL = 156543.03392;

    /** The viewport Google's own share dialog writes into the embed. */
    private const VIEWPORT_WIDTH = 1024;

    private const VIEWPORT_HEIGHT = 768;

    /** A place id is two hex numbers joined by a colon, and nothing else. */
    private const PLACE_ID_PATTERN = '/^0x[0-9a-f]+:0x[0-9a-f]+$/i';

    /**
     * @param  string|null  $query  coordinates ("19.76, 96.21") or an address
     * @param  string|null  $placeId  Google's listing id, "0x…:0x…"
     * @param  string|null  $label  the name written on the listing card
     */
    public function __construct(
        private readonly ?string $query = null,
        ?string $placeId = null,
        private readonly int $zoom = self::DEFAULT_ZOOM,
        private readonly ?string $locale = null,
        private readonly ?float $latitude = null,
        private readonly ?float $longitude = null,
        private readonly ?string $label = null,
    ) {
        // Validated on the way in, not on the way out: a value that is not an
        // id — a pasted embed URL, a ChIJ id, a sentence — must never reach the
        // `pb` string, because Google answers a malformed one with 400 and the
        // visitor gets an empty frame instead of a map.
        $this->placeId = self::placeId($placeId);
    }

    /** Google's listing id, when the value really is one. */
    private readonly ?string $placeId;

    /**
     * The map as the site is configured right now, with optional per-block
     * overrides — a page can point at a different office without touching the
     * shared settings.
     *
     * @param  array<string, mixed>  $data  the block payload
     */
    public static function fromSettings(array $data = []): self
    {
        $query = trim((string) ($data['query'] ?? ''))
            ?: Setting::get('company.map_query')
            ?: Setting::get('company.address');

        return new self(
            query: $query !== '' ? $query : null,
            placeId: self::setting($data, 'place_id', 'company.map_place_id'),
            zoom: self::clampZoom((int) ($data['zoom'] ?? '') ?: Setting::integer('company.map_zoom', self::DEFAULT_ZOOM)),
            locale: app()->getLocale(),
            latitude: self::coordinate($data['latitude'] ?? null)
                ?? self::coordinate(Setting::get('company.latitude'))
                ?? self::coordinateFrom($query, 0),
            longitude: self::coordinate($data['longitude'] ?? null)
                ?? self::coordinate(Setting::get('company.longitude'))
                ?? self::coordinateFrom($query, 1),
            label: trim((string) ($data['label'] ?? '')) ?: Setting::get('company.name'),
        );
    }

    public static function clampZoom(int $zoom): int
    {
        return max(self::MIN_ZOOM, min(self::MAX_ZOOM, $zoom));
    }

    public function isConfigured(): bool
    {
        return filled($this->query) || ($this->latitude !== null && $this->longitude !== null);
    }

    /** The address for the iframe, or null when there is nothing to show. */
    public function embedUrl(): ?string
    {
        if (! $this->isConfigured()) {
            return null;
        }

        if ($this->placeId !== null && $this->latitude !== null && $this->longitude !== null) {
            return $this->listingEmbedUrl();
        }

        return $this->searchEmbedUrl();
    }

    /**
     * The share-dialog address, rebuilt from the settings.
     *
     * The `pb` string is a compressed protobuf and Google validates it, so its
     * shape is not free-form: it is reproduced exactly as the share dialog
     * emits it, with only the values that belong to the site substituted —
     * latitude, longitude, the span that matches the zoom, the place id, the
     * name on the card, and the language of the interface. The place segment is
     * mandatory: dropping it, or inventing an id, is answered with 400.
     */
    private function listingEmbedUrl(): string
    {
        $pb = implode('', [
            '!1m18!1m12!1m3!1d'.$this->span(),
            '!2d'.$this->decimal($this->longitude),
            '!3d'.$this->decimal($this->latitude),
            '!2m3!1f0!2f0!3f0!3m2!1i'.self::VIEWPORT_WIDTH.'!2i'.self::VIEWPORT_HEIGHT,
            '!4f13.1',
            '!3m3!1m2!1s'.rawurlencode((string) $this->placeId),
            '!2s'.rawurlencode((string) ($this->label ?: $this->query)),
            '!5e1',
            '!3m2!1s'.$this->language().'!2s'.$this->region(),
            '!4v'.now()->getTimestampMs(),
            '!5m2!1s'.$this->language().'!2s'.$this->region(),
        ]);

        return 'https://www.google.com/maps/embed?pb='.$pb;
    }

    /** The key-free search form: a pin, no listing, no key. */
    private function searchEmbedUrl(): string
    {
        $query = $this->query;

        if ($query === null && $this->latitude !== null && $this->longitude !== null) {
            $query = $this->decimal($this->latitude).','.$this->decimal($this->longitude);
        }

        return 'https://maps.google.com/maps?'.http_build_query([
            'q' => $query,
            'z' => (string) $this->zoom,
            'hl' => $this->language(),
            'output' => 'embed',
        ]);
    }

    /**
     * How much ground the viewport covers, in metres.
     *
     * Google echoes this back as the camera span, and it is the only place the
     * zoom and the latitude meet: 1024 px across a latitude-shrunk world.
     */
    private function span(): string
    {
        $metres = self::VIEWPORT_WIDTH
            * self::METRES_PER_PIXEL
            * cos(deg2rad((float) $this->latitude))
            / (2 ** $this->zoom);

        return rtrim(rtrim(sprintf('%.4F', $metres), '0'), '.');
    }

    /**
     * The "open in Google Maps" destination — the URL format Google documents
     * for linking out, which hands the visitor a real map with directions.
     *
     * The coordinates are used rather than the place id: Google's
     * `query_place_id` wants the `ChIJ…` form, while the id the share dialog
     * gives out for an embed is the `0x…:0x…` pair, and passing that one here
     * sends the visitor to an empty search.
     */
    public function externalUrl(): ?string
    {
        if (! $this->isConfigured()) {
            return null;
        }

        $params = ['api' => '1'];

        if ($this->latitude !== null && $this->longitude !== null) {
            $params['query'] = $this->decimal($this->latitude).','.$this->decimal($this->longitude);
        } else {
            $params['query'] = (string) $this->query;
        }

        if (($locale = $this->localeOrNull()) !== null) {
            $params['hl'] = $locale;
        }

        return 'https://www.google.com/maps/search/?'.http_build_query($params);
    }

    public function zoom(): int
    {
        return $this->zoom;
    }

    /** What the map is pointing at, for the link text and alt copy. */
    public function location(): ?string
    {
        if ($this->latitude !== null && $this->longitude !== null) {
            return $this->decimal($this->latitude).', '.$this->decimal($this->longitude);
        }

        return $this->query;
    }

    /** Coordinates are printed plainly: no exponent, no trailing zeros. */
    private function decimal(?float $value): string
    {
        return rtrim(rtrim(sprintf('%.7F', (float) $value), '0'), '.');
    }

    /**
     * The interface language, kept to the ones this site publishes. Google
     * falls back on its own for anything else, but sending a value it has no
     * copy for would only make the embed heavier.
     */
    private function language(): string
    {
        return in_array($this->localeOrNull(), ['en', 'my', 'ru'], true) ? (string) $this->locale : 'en';
    }

    /** The region the map labels are drawn for. */
    private function region(): string
    {
        return Setting::get('company.country') ?: 'mm';
    }

    private function localeOrNull(): ?string
    {
        return filled($this->locale) ? (string) $this->locale : null;
    }

    /** A place id, or null when the value is a pasted URL or something else. */
    private static function placeId(mixed $value): ?string
    {
        $value = trim((string) $value);

        return preg_match(self::PLACE_ID_PATTERN, $value) === 1 ? $value : null;
    }

    /** A block override when the editor filled one in, the setting otherwise. */
    private static function setting(array $data, string $key, string $setting): mixed
    {
        $value = $data[$key] ?? null;

        return filled($value) ? $value : Setting::get($setting);
    }

    private static function coordinate(mixed $value): ?float
    {
        $value = trim((string) $value);

        return is_numeric($value) ? (float) $value : null;
    }

    /** Pull a coordinate out of "19.76, 96.21", which older rows stored. */
    private static function coordinateFrom(?string $query, int $index): ?float
    {
        if ($query === null) {
            return null;
        }

        $parts = array_map('trim', explode(',', $query));

        return isset($parts[$index]) && is_numeric($parts[$index]) ? (float) $parts[$index] : null;
    }
}
