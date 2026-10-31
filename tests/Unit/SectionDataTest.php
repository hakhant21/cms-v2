<?php

namespace Tests\Unit;

use App\Blocks\BlockRegistry;
use App\Blocks\Types\Section;
use App\Support\SectionData;
use PHPUnit\Framework\TestCase;

/**
 * The block layer is what the admin writes and the site renders, so these
 * tests pin the parts an editor relies on: which layouts exist, and how an
 * unordered field list is read.
 */
class SectionDataTest extends TestCase
{
    public function test_a_section_with_no_fields_has_nothing_to_show(): void
    {
        $section = SectionData::make(['layout' => 'plain', 'fields' => []]);

        $this->assertSame([], $section->fields());
        $this->assertSame('plain', $section->layout());
    }

    public function test_empty_lists_are_dropped_so_no_blank_cards_render(): void
    {
        $section = SectionData::make([
            'fields' => [
                ['type' => 'title', 'value' => 'Soil'],
                ['type' => 'list', 'items' => []],
                ['type' => 'stats', 'items' => [['value' => '12', 'title' => 'Farms']]],
            ],
        ]);

        $this->assertSame(['title', 'stats'], array_column($section->fields(), 'type'));
    }

    public function test_text_fields_keep_their_order(): void
    {
        $section = SectionData::make([
            'fields' => [
                ['type' => 'eyebrow', 'value' => 'About'],
                ['type' => 'title', 'value' => 'Who we are'],
                ['type' => 'rich_text', 'value' => '<p>Body</p>'],
                ['type' => 'image', 'value' => 'pages/farm.jpg'],
            ],
        ]);

        $this->assertSame(['eyebrow', 'title', 'rich_text'], array_column($section->textFields(), 'type'));
        $this->assertSame('pages/farm.jpg', $section->mediaField()['value']);
    }

    public function test_a_signature_layout_falls_back_when_its_content_is_missing(): void
    {
        $section = SectionData::make([
            'layout' => 'company_profile',
            'fields' => [['type' => 'title', 'value' => 'About us']],
        ]);

        $this->assertSame('plain', $section->layout());
    }

    public function test_a_signature_layout_survives_when_its_content_is_present(): void
    {
        $section = SectionData::make([
            'layout' => 'company_profile',
            'fields' => [
                ['type' => 'profile', 'value' => 'Founded in Russia'],
                ['type' => 'mission', 'value' => 'Better soil'],
            ],
        ]);

        $this->assertSame('company_profile', $section->layout());
    }

    public function test_a_feature_list_with_dash_headings_is_read_as_groups(): void
    {
        // The product feature lists arrived from the old site as one flat list
        // whose section titles are marked with a leading em dash. Turning that
        // into numbered cards would make every heading one more card, so the
        // renderer is given the grouped shape with the marker stripped off.
        $section = SectionData::make([
            'layout' => 'grid',
            'fields' => [[
                'type' => 'list',
                'items' => [
                    ['text' => '— Key Features'],
                    ['text' => 'Fast green-up'],
                    ['text' => 'Strong roots'],
                    ['text' => '— Why farmers choose it'],
                    ['text' => 'Higher yield'],
                ],
            ]],
        ]);

        $groups = $section->groups($section->field('list'));

        $this->assertNotNull($groups);
        $this->assertSame(['Key Features', 'Why farmers choose it'], array_column($groups, 'label'));
        $this->assertSame(['Fast green-up', 'Strong roots'], $groups[0]['items']);
        $this->assertSame(['Higher yield'], $groups[1]['items']);
    }

    public function test_an_ordinary_card_list_is_not_grouped(): void
    {
        // A grid whose rows have no heading marker — or whose first row is a
        // bullet rather than a heading — stays the numbered cards it has always
        // been, so home and about are untouched by the grouping.
        $cards = SectionData::make([
            'fields' => [[
                'type' => 'list',
                'items' => [
                    ['title' => 'Soil', 'text' => 'Healthier ground'],
                    ['title' => 'Yield', 'text' => 'More per acre'],
                ],
            ]],
        ]);

        $this->assertNull($cards->groups($cards->field('list')));
    }

    public function test_a_bullet_before_the_first_heading_disables_grouping(): void
    {
        $section = SectionData::make([
            'fields' => [[
                'type' => 'list',
                'items' => [
                    ['text' => 'A stray lead line'],
                    ['text' => '— Key Features'],
                    ['text' => 'Fast green-up'],
                ],
            ]],
        ]);

        $this->assertNull($section->groups($section->field('list')));
    }

    public function test_the_editor_offers_every_layout_the_renderer_understands(): void
    {
        $directory = dirname(__DIR__, 2).'/resources/views/blocks/sections';

        foreach (array_keys(Section::LAYOUTS) as $layout) {
            $this->assertFileExists(
                "{$directory}/{$layout}.blade.php",
                "The editor offers the [{$layout}] layout but no section view renders it.",
            );
        }
    }

    public function test_every_registered_block_has_a_view_and_a_unique_key(): void
    {
        $registry = new BlockRegistry;
        $keys = array_keys($registry->all());

        $this->assertSame($keys, array_unique($keys));

        foreach (array_keys($registry->all()) as $key) {
            $this->assertFileExists(
                dirname(__DIR__, 2)."/resources/views/blocks/{$key}.blade.php",
                "The [{$key}] block is registered but has no view.",
            );
        }
    }
}
