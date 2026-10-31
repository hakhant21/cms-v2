<?php

namespace Tests\Unit;

use App\Support\ProjectBrief;
use PHPUnit\Framework\TestCase;

/**
 * A project is written as one rich-text field, and the showcase layout has to
 * hand each piece of it to the right place. These tests pin the reading order
 * and the two regressions that motivated the parser: a list's lead-in sentence
 * left dangling, and a list typed as dash-prefixed paragraphs.
 */
class ProjectBriefTest extends TestCase
{
    public function test_the_sentence_introducing_a_list_stays_with_it(): void
    {
        $brief = ProjectBrief::fromHtml(
            '<h1>Composts</h1>'
            .'<p>Ruma Organic is developing a new compost project.</p>'
            .'<p>Our compost comes in two types:</p>'
            .'<ul><li>Quick Compost – ready in just 5–7 days</li><li>Mature Compost – fully developed in 28 days</li></ul>'
            .'<p>Farmers use 4–5 times less compost.</p>'
        );

        $this->assertSame('Ruma Organic is developing a new compost project.', $brief->lead);
        $this->assertSame('Our compost comes in two types:', $brief->listIntro);
        $this->assertSame('<p>Farmers use 4–5 times less compost.</p>', $brief->body);
        $this->assertCount(2, $brief->highlights);
    }

    public function test_a_named_highlight_splits_into_a_label_and_a_detail(): void
    {
        $brief = ProjectBrief::fromHtml('<ul><li>Quick Compost – ready in just 5–7 days</li></ul>');

        $this->assertSame(
            ['label' => 'Quick Compost', 'text' => 'ready in just 5–7 days'],
            $brief->highlights[0],
        );
    }

    public function test_a_range_inside_a_highlight_is_not_taken_for_a_separator(): void
    {
        $brief = ProjectBrief::fromHtml('<ul><li>30% less irrigation water needed</li></ul>');

        $this->assertSame(['label' => null, 'text' => '30% less irrigation water needed'], $brief->highlights[0]);
    }

    public function test_paragraphs_typed_as_dash_items_become_highlights(): void
    {
        $brief = ProjectBrief::fromHtml(
            '<p>A new generation of biological products.</p>'
            .'<p>– Protects crops from diseases</p>'
            .'<p>– Improves soil health</p>'
            .'<p>Nature has its own defense.</p>'
        );

        $this->assertSame('A new generation of biological products.', $brief->lead);
        $this->assertSame([
            ['label' => null, 'text' => 'Protects crops from diseases'],
            ['label' => null, 'text' => 'Improves soil health'],
        ], $brief->highlights);
        $this->assertSame('<p>Nature has its own defense.</p>', $brief->body);
    }

    public function test_the_heading_is_dropped_because_the_page_title_carries_it(): void
    {
        $brief = ProjectBrief::fromHtml('<h1>Seed production project</h1><p>Testing varieties across Myanmar.</p>');

        $this->assertSame('Testing varieties across Myanmar.', $brief->lead);
        $this->assertSame('', $brief->body);
    }

    public function test_prose_without_a_list_keeps_its_order_in_the_body(): void
    {
        $brief = ProjectBrief::fromHtml('<p>Lead.</p><p>Second.</p><p>Third.</p>');

        $this->assertSame('Lead.', $brief->lead);
        $this->assertNull($brief->listIntro);
        $this->assertSame([], $brief->highlights);
        $this->assertSame('<p>Second.</p><p>Third.</p>', $brief->body);
    }

    public function test_a_paragraph_inside_a_list_item_is_not_read_as_prose(): void
    {
        $brief = ProjectBrief::fromHtml('<p>Lead.</p><ul><li><p>Inside the item</p></li></ul>');

        $this->assertSame('Lead.', $brief->lead);
        $this->assertSame('', $brief->body);
        $this->assertSame([['label' => null, 'text' => 'Inside the item']], $brief->highlights);
    }

    public function test_an_empty_field_has_nothing_to_show(): void
    {
        $this->assertTrue(ProjectBrief::fromHtml('')->isEmpty());
        $this->assertTrue(ProjectBrief::fromBlock([])->isEmpty());
    }

    public function test_the_first_rich_text_field_of_a_block_is_the_story(): void
    {
        $brief = ProjectBrief::fromBlock([
            'type' => 'section',
            'data' => [
                'layout' => 'plain',
                'fields' => [
                    ['type' => 'title', 'value' => 'Ignored'],
                    ['type' => 'rich_text', 'value' => '<p>The story.</p>'],
                ],
            ],
        ]);

        $this->assertSame('The story.', $brief->lead);
    }
}
