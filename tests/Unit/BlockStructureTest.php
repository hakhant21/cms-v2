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
