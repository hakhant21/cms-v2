<?php

namespace Tests\Feature;

use App\Models\Language;
use App\Models\Page;
use App\Models\Setting;
use App\Support\GoogleMap;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * The map is the one part of the site that leans on a third-party service, so
 * it is built from Google's key-free embed address: it must draw without any
 * API key or billing account, and it must degrade to a plain link rather than a
 * broken frame when nothing is configured.
 */
class GoogleMapTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_free_embed_is_used_for_coordinates(): void
    {
        $map = new GoogleMap(query: '19.7616782, 96.2196115', zoom: 16, locale: 'en');

        $url = $map->embedUrl();

        $this->assertNotNull($url);
        $this->assertStringStartsWith('https://maps.google.com/maps?', $url);
        $this->assertStringContainsString('q=19.7616782%2C+96.2196115', $url);
        $this->assertStringContainsString('z=16', $url);
        $this->assertStringContainsString('output=embed', $url);
        // Google's own labels follow the language the page is served in.
        $this->assertStringContainsString('hl=en', $url);
    }

    public function test_a_place_id_draws_the_business_listing_instead_of_a_pin(): void
    {
        Setting::put('company.country', 'MM', 'company');
        Setting::forgetCache();

        $map = new GoogleMap(
            query: 'No. 12, Nay Pyi Taw',
            placeId: '0x30c8b9001d3e3443:0x7e68357b00be8b21',
            zoom: 16,
            locale: 'en',
            latitude: 19.7616782,
            longitude: 96.2196115,
            label: 'RUMA Organic',
        );

        $embed = (string) $map->embedUrl();

        // The share-dialog address, which carries the listing itself. The
        // search form is what produced the "place info couldn't load" strip.
        $this->assertStringStartsWith('https://www.google.com/maps/embed?pb=!', $embed);
        $this->assertStringNotContainsString('output=embed', $embed);
        $this->assertStringContainsString('!2d96.2196115', $embed);
        $this->assertStringContainsString('!3d19.7616782', $embed);
        $this->assertStringContainsString('!4f13.1', $embed);
        // The colon in the place id is what Google expects, percent-encoded.
        $this->assertStringContainsString('%3A0x7e68357b00be8b21', $embed);
        $this->assertStringContainsString('!2sRUMA%20Organic!', $embed);
        $this->assertStringContainsString('!2sMM', $embed);
    }

    public function test_the_camera_span_follows_the_zoom_and_the_latitude(): void
    {
        $map = new GoogleMap(
            placeId: '0x1:0x2',
            zoom: 16,
            locale: 'en',
            latitude: 19.7616782,
            longitude: 96.2196115,
        );

        // 1024 px across a world that shrinks with the cosine of the latitude:
        // a span that disagrees with the zoom frames the wrong patch of ground.
        $this->assertStringContainsString('!1d2301.9338', (string) $map->embedUrl());
    }

    #[DataProvider('notPlaceIds')]
    public function test_a_value_that_is_not_a_place_id_falls_back_to_the_search_form(string $value): void
    {
        $map = new GoogleMap(query: 'Nay Pyi Taw', placeId: $value, latitude: 19.76, longitude: 96.21);

        $this->assertStringContainsString('maps.google.com/maps?', (string) $map->embedUrl());
    }

    /**
     * @return array<string, array{string}>
     */
    public static function notPlaceIds(): array
    {
        return [
            'a pasted share URL' => ['https://maps.google.com/maps?q=RUMA&output=embed'],
            'a ChIJ id, which the embed does not take' => ['ChIJQzQ-HQC5yDARIYu-AHs1aH4'],
            'prose' => ['somewhere near the market'],
        ];
    }

    public function test_linking_out_uses_coordinates_rather_than_the_embed_place_id(): void
    {
        $this->assertSame(
            'https://www.google.com/maps/search/?api=1&query=19.76%2C+96.21&hl=ru',
            (new GoogleMap(query: '19.76, 96.21', locale: 'ru'))->externalUrl(),
        );

        // `query_place_id` wants the `ChIJ…` form; the embed's `0x…:0x…` pair
        // there is an empty search, so the exact coordinates are used instead.
        $this->assertSame(
            'https://www.google.com/maps/search/?api=1&query=19.7616782%2C96.2196115',
            (new GoogleMap(placeId: '0x1:0x2', latitude: 19.7616782, longitude: 96.2196115))->externalUrl(),
        );
    }

    public function test_nothing_is_configured_no_map_and_no_link(): void
    {
        $map = new GoogleMap;

        $this->assertFalse($map->isConfigured());
        $this->assertNull($map->embedUrl());
        $this->assertNull($map->externalUrl());
    }

    #[DataProvider('zoomLevels')]
    public function test_zoom_stays_inside_the_range_google_draws(int $given, int $expected): void
    {
        $this->assertSame($expected, GoogleMap::clampZoom($given));
    }

    /**
     * @return array<string, array{int, int}>
     */
    public static function zoomLevels(): array
    {
        return [
            'below the tiles' => [1, GoogleMap::MIN_ZOOM],
            'above the tiles' => [40, GoogleMap::MAX_ZOOM],
            'a sensible street' => [16, 16],
            'unset' => [0, GoogleMap::MIN_ZOOM],
        ];
    }

    public function test_the_site_setting_is_used_unless_a_block_overrides_it(): void
    {
        Setting::put('company.map_query', '20.0000, 94.0000', 'company');
        Setting::put('company.map_zoom', '12', 'company');
        Setting::forgetCache();

        $fromSettings = GoogleMap::fromSettings();
        $overridden = GoogleMap::fromSettings(['query' => '21.0000, 95.0000', 'zoom' => '18']);

        $this->assertStringContainsString('z=12', (string) $fromSettings->embedUrl());
        $this->assertStringContainsString('20.0000', (string) $fromSettings->embedUrl());
        $this->assertStringContainsString('z=18', (string) $overridden->embedUrl());
        $this->assertStringContainsString('21.0000', (string) $overridden->embedUrl());
    }

    public function test_the_address_draws_the_map_when_nobody_entered_coordinates(): void
    {
        Setting::put('company.address', 'No. 12, Nay Pyi Taw', 'company');
        Setting::forgetCache();

        $this->assertStringContainsString('No.+12', (string) GoogleMap::fromSettings()->embedUrl());
    }

    public function test_a_page_with_a_map_block_shows_google_maps_and_a_way_to_open_it(): void
    {
        Language::query()->create(['code' => 'en', 'name' => 'English', 'is_default' => true, 'sort_order' => 1]);
        Language::forgetCache();

        Setting::put('company.map_query', '19.7616782, 96.2196115', 'company');
        Setting::forgetCache();

        $page = Page::query()->create([
            'kind' => Page::KIND_PAGE,
            'slug' => 'find-us',
            'status' => Page::STATUS_PUBLISHED,
            'published_at' => now(),
        ]);

        $page->translations()->create([
            'locale' => 'en',
            'title' => 'Find us',
            'content_blocks' => [['type' => 'map', 'data' => ['title' => 'Come and see the farm']]],
        ]);

        $this->get('/find-us')
            ->assertOk()
            ->assertSee('Come and see the farm')
            ->assertSee('maps.google.com/maps?', false)
            ->assertSee('19.7616782', false)
            ->assertSee('google.com/maps/search/?api=1', false)
            ->assertSee('View on Google Maps');
    }
}
