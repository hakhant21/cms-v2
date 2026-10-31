<?php

namespace App\Support;

/**
 * A "next project" story, taken apart for the showcase layout.
 *
 * Editors write a project as one rich-text field: a heading, a lead sentence,
 * a bullet list and a closing thought. Rendering that verbatim gives a wall of
 * text, and lifting the list out with a regex leaves the sentence that
 * introduces it ("Our compost comes in two types:") dangling above nothing.
 * So the field is read in document order and each piece is handed to the part
 * of the layout that can carry it: the lead beside the picture, the list as
 * cards under its own introduction, the rest as prose.
 *
 * Nothing here is specific to one project or one language. A list written as
 * dash-prefixed paragraphs is recognised too, because that is how several of
 * the imported projects were typed.
 */
final class ProjectBrief
{
    /**
     * @param  list<array{label: string|null, text: string}>  $highlights
     */
    private function __construct(
        public readonly ?string $lead,
        public readonly ?string $listIntro,
        public readonly array $highlights,
        public readonly string $body,
    ) {}

    /**
     * The first rich-text field of a section block, read as a project story.
     *
     * @param  array<string, mixed>  $block
     */
    public static function fromBlock(array $block): self
    {
        $fields = $block['data']['fields'] ?? [];

        foreach (is_array($fields) ? $fields : [] as $field) {
            if (($field['type'] ?? null) === 'rich_text' && is_string($field['value'] ?? null)) {
                return self::fromHtml($field['value']);
            }
        }

        return self::fromHtml('');
    }

    public static function fromHtml(string $html): self
    {
        $lead = null;
        $listIntro = null;
        $highlights = [];
        $body = '';

        // The paragraph just read is held back one step: only the block that
        // follows it can say whether it introduces a list or is ordinary prose.
        $pending = null;

        foreach (self::blocks($html) as $node) {
            $tag = $node['tag'];

            // The page title is the heading; a second one inside the copy
            // would repeat it.
            if ($tag === 'h1') {
                continue;
            }

            if ($tag === 'ul' || $tag === 'ol') {
                $items = self::items($node['inner']);

                if ($items !== []) {
                    if ($pending !== null) {
                        $listIntro = $pending['text'];
                        $pending = null;
                    }

                    foreach ($items as $item) {
                        $highlights[] = self::highlight($item);
                    }
                }

                continue;
            }

            if ($tag === 'p') {
                $text = trim(strip_tags($node['inner']));

                if ($text === '') {
                    continue;
                }

                // A paragraph typed as "– Protects crops…" is a list item.
                if (preg_match('/^[–—−\-•*]\s+(.*)$/us', $text, $match) === 1) {
                    if ($pending !== null) {
                        $body .= $pending['html'];
                        $pending = null;
                    }

                    $highlights[] = self::highlight(trim($match[1]));

                    continue;
                }

                if ($lead === null && $highlights === [] && $listIntro === null) {
                    $lead = $text;

                    continue;
                }

                if ($pending !== null) {
                    $body .= $pending['html'];
                }

                $pending = ['text' => $text, 'html' => $node['html']];

                continue;
            }

            // Sub-headings, quotes and anything else keep their place in the
            // prose, with the held paragraph flushed in front of them.
            if ($pending !== null) {
                $body .= $pending['html'];
                $pending = null;
            }

            $body .= $node['html'];
        }

        if ($pending !== null) {
            $body .= $pending['html'];
        }

        return new self($lead, $listIntro, $highlights, trim($body));
    }

    /**
     * Whether the story has anything at all, so an empty field renders nothing
     * rather than an empty showcase.
     */
    public function isEmpty(): bool
    {
        return $this->lead === null && $this->highlights === [] && $this->body === '';
    }

    /**
     * Top-level blocks in the order they were written.
     *
     * The scan consumes each block whole, so a paragraph inside a list item is
     * never mistaken for a paragraph of the story.
     *
     * @return list<array{tag: string, html: string, inner: string}>
     */
    private static function blocks(string $html): array
    {
        $nodes = [];
        $offset = 0;

        while (preg_match('/<(h1|h2|h3|h4|p|ul|ol|blockquote)\b[^>]*>/is', $html, $open, PREG_OFFSET_CAPTURE, $offset) === 1) {
            $tag = strtolower($open[1][0]);
            $start = (int) $open[0][1];
            $innerStart = $start + strlen($open[0][0]);
            $close = stripos($html, '</'.$tag.'>', $innerStart);

            if ($close === false) {
                break;
            }

            $nodes[] = [
                'tag' => $tag,
                'html' => substr($html, $start, $close + strlen($tag) + 3 - $start),
                'inner' => substr($html, $innerStart, $close - $innerStart),
            ];

            $offset = $close + strlen($tag) + 3;
        }

        return $nodes;
    }

    /**
     * The text of each list item, with markup dropped.
     *
     * @return list<string>
     */
    private static function items(string $inner): array
    {
        if (preg_match_all('/<li[^>]*>(.*?)<\/li>/is', $inner, $matches) === false) {
            return [];
        }

        return array_values(array_filter(array_map(
            fn (string $item): string => trim(strip_tags($item)),
            $matches[1],
        ), fn (string $item): bool => $item !== ''));
    }

    /**
     * One highlight, split into a name and what it does.
     *
     * "Quick Compost – ready in just 5–7 days" reads as a card with a bold
     * title and a lighter line under it. The separator must have space on
     * both sides, or the range inside "5–7 days" would be taken for one.
     *
     * @return array{label: string|null, text: string}
     */
    private static function highlight(string $text): array
    {
        if (preg_match('/^(.+?)\s+[–—−\-:]\s+(.+)$/us', $text, $match) === 1) {
            return ['label' => trim($match[1]), 'text' => trim($match[2])];
        }

        return ['label' => null, 'text' => $text];
    }
}
