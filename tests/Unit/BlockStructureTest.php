<?php

namespace Tests\Unit;

use App\Support\BlockStructure;
use PHPUnit\Framework\TestCase;

/**
 * The page editor lets one language tab own the section structure, and this
 * is what re-fits the other languages' words into it. The behaviour editors
 * rely on: delete once and it is gone everywhere, keep the translations of
 * everything that stays.
 */
class BlockStructureTest extends TestCase
{
    public function test_deleting_a_section_in_the_main_language_removes_it_in_every_language(): void
    {
        $canonical = [
            self::section('hero'),
        ];
        $locale = [
            self::section('hero', 'မြန်မာ'),
            self::section('plain', 'မြန်မာ'),
        ];

        $aligned = BlockStructure::align($canonical, $locale);

        $this::assertCount(1, $aligned);
        $this::assertSame('hero', $aligned[0]['data']['layout']);
    }

    public function test_sections_that_stay_keep_their_translated_words(): void
    {
        $aligned = BlockStructure::align(
            [self::section('hero'), self::section('plain')],
            [self::section('hero', 'my text'), self::section('plain', 'translated')],
        );

        $this::assertSame('translated', $aligned[1]['data']['fields'][0]['value']);
    }

    public function test_a_new_section_arrives_in_the_main_language_ready_to_translate(): void
    {
        $aligned = BlockStructure::align(
            [self::section('hero'), self::section('cta', 'English words')],
            [self::section('hero', 'my text')],
        );

        $this::assertCount(2, $aligned);
        $this::assertSame('cta', $aligned[1]['data']['layout']);
        $this::assertSame('English words', $aligned[1]['data']['fields'][0]['value']);
        $this::assertSame('my text', $aligned[0]['data']['fields'][0]['value']);
    }

    public function test_reordering_the_main_language_moves_the_translations_with_it(): void
    {
        $aligned = BlockStructure::align(
            [self::section('plain'), self::section('hero')],
            [self::section('hero', 'hero words'), self::section('plain', 'plain words')],
        );

        $this::assertSame('plain words', $aligned[0]['data']['fields'][0]['value']);
        $this::assertSame('hero words', $aligned[1]['data']['fields'][0]['value']);
    }

    public function test_identical_sections_are_matched_in_order_not_stolen_by_a_looser_match(): void
    {
        // The first canonical section is brand new (no fields), the second is
        // an exact copy of the locale's plain section. A sloppy single pass
        // would let the new one claim the translated words.
        $fresh = ['type' => 'section', 'data' => ['layout' => 'plain', 'fields' => []]];

        $aligned = BlockStructure::align(
            [$fresh, self::section('plain', 'English')],
            [self::section('plain', 'translated')],
        );

        $this::assertSame('translated', $aligned[1]['data']['fields'][0]['value']);
        $this::assertSame($fresh, $aligned[0]);
    }

    public function test_editing_a_field_keeps_the_words_of_the_same_section(): void
    {
        // An editor added an eyebrow to the hero here; the layout is still
        // the same section, so Burmese keeps its text instead of resetting.
        $aligned = BlockStructure::align(
            [self::section('hero', 'English', fields: ['eyebrow', 'title'])],
            [self::section('hero', 'translated', fields: ['title'])],
        );

        $this::assertSame('translated', $aligned[0]['data']['fields'][0]['value']);
    }

    public function test_garbage_in_the_state_does_not_crash_the_realignment(): void
    {
        $aligned = BlockStructure::align(
            ['not a block', self::section('hero')],
            [self::section('hero', 'translated')],
        );

        $this::assertCount(2, $aligned);
        $this::assertSame('translated', $aligned[1]['data']['fields'][0]['value']);
    }

    public function test_mirror_copies_media_and_links_but_keeps_the_translated_words(): void
    {
        // Upload once: the image, the button's destination and the layout
        // belong to the page, while the title, the alt text and the button
        // label stay in the locale's own words.
        $canonical = [[
            'type' => 'section',
            'data' => [
                'layout' => 'media',
                'fields' => [
                    ['type' => 'title', 'value' => 'English'],
                    ['type' => 'image', 'value' => 'pages/new.jpg', 'alt' => 'A farm'],
                    ['type' => 'button', 'value' => 'Read more', 'url' => '/about'],
                ],
            ],
        ]];
        $locale = [[
            'type' => 'section',
            'data' => [
                'layout' => 'plain',
                'fields' => [
                    ['type' => 'title', 'value' => 'မြန်မာ'],
                    ['type' => 'image', 'value' => 'pages/old.jpg', 'alt' => 'လယ်ယာ'],
                    ['type' => 'button', 'value' => 'ဆက်ဖတ်ရန်', 'url' => '/old'],
                ],
            ],
        ]];

        $mirrored = BlockStructure::mirror($canonical, $locale);
        $fields = $mirrored[0]['data']['fields'];

        $this::assertSame('media', $mirrored[0]['data']['layout']);
        $this::assertSame('မြန်မာ', $fields[0]['value']);
        $this::assertSame('pages/new.jpg', $fields[1]['value']);
        $this::assertSame('လယ်ယာ', $fields[1]['alt'], 'A caption the locale already wrote is kept.');
        $this::assertSame('ဆက်ဖတ်ရန်', $fields[2]['value']);
        $this::assertSame('/about', $fields[2]['url']);
    }

    public function test_mirror_labels_a_fresh_image_with_the_source_alt(): void
    {
        $canonical = [[
            'type' => 'section',
            'data' => ['layout' => 'plain', 'fields' => [
                ['type' => 'image', 'value' => 'pages/new.jpg', 'alt' => 'A farm'],
            ]],
        ]];
        $locale = [[
            'type' => 'section',
            'data' => ['layout' => 'plain', 'fields' => [
                ['type' => 'image', 'value' => '', 'alt' => ''],
            ]],
        ]];

        $mirrored = BlockStructure::mirror($canonical, $locale);

        $this::assertSame('pages/new.jpg', $mirrored[0]['data']['fields'][0]['value']);
        $this::assertSame('A farm', $mirrored[0]['data']['fields'][0]['alt']);
    }

    public function test_mirror_carries_gallery_sources_across_and_keeps_translated_captions(): void
    {
        $canonical = [[
            'type' => 'section',
            'data' => ['layout' => 'plain', 'fields' => [
                ['type' => 'gallery', 'items' => [
                    ['src' => 'pages/a.jpg', 'alt' => 'One'],
                    ['src' => 'pages/b.jpg', 'alt' => 'Two'],
                ]],
            ]],
        ]];
        $locale = [[
            'type' => 'section',
            'data' => ['layout' => 'plain', 'fields' => [
                ['type' => 'gallery', 'items' => [
                    ['src' => 'pages/old.jpg', 'alt' => 'တစ်'],
                ]],
            ]],
        ]];

        $mirrored = BlockStructure::mirror($canonical, $locale);
        $items = $mirrored[0]['data']['fields'][0]['items'];

        $this::assertCount(2, $items, 'A photo added to the gallery reaches every language.');
        $this::assertSame('pages/a.jpg', $items[0]['src']);
        $this::assertSame('တစ်', $items[0]['alt']);
        $this::assertSame('pages/b.jpg', $items[1]['src']);
        $this::assertSame('Two', $items[1]['alt']);
    }

    public function test_mirror_matches_fields_by_type_across_mixed_key_formats(): void
    {
        // Stored rows are integer-keyed while editor-written blocks can be
        // uuid-keyed; the match must survive both.
        $canonical = [[
            'type' => 'section',
            'data' => ['layout' => 'plain', 'fields' => [
                'f47ac10b-58cc-4372-a567-0e02b2c3d479' => ['type' => 'title', 'value' => 'English'],
                '9b2d1c4e-1f2a-4c3d-9e8f-abcdef123456' => ['type' => 'image', 'value' => 'pages/new.jpg'],
            ]],
        ]];
        $locale = [[
            'type' => 'section',
            'data' => ['layout' => 'plain', 'fields' => [
                ['type' => 'title', 'value' => 'မြန်မာ'],
                ['type' => 'image', 'value' => 'pages/old.jpg'],
            ]],
        ]];

        $mirrored = BlockStructure::mirror($canonical, $locale);
        $fields = $mirrored[0]['data']['fields'];

        $this::assertSame('မြန်မာ', $fields['f47ac10b-58cc-4372-a567-0e02b2c3d479']['value']);
        $this::assertSame('pages/new.jpg', $fields['9b2d1c4e-1f2a-4c3d-9e8f-abcdef123456']['value']);
    }

    /**
     * @param  array<int, string>  $fields
     * @return array<string, mixed>
     */
    private static function section(string $layout, string $value = 'English', array $fields = ['title']): array
    {
        return [
            'type' => 'section',
            'data' => [
                'layout' => $layout,
                'fields' => array_map(
                    fn (string $type): array => ['type' => $type, 'value' => $value],
                    $fields,
                ),
            ],
        ];
    }
}
