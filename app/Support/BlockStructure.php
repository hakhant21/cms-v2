<?php

namespace App\Support;

/**
 * Keeps every language tab of the page editor showing the same sections.
 *
 * Sections are managed once, in the page's default language; the other
 * languages only hold words. When the structure changes there, each locale's
 * blocks are re-fitted into it: a block that still exists keeps its translated
 * text, one that was removed disappears in every language, and a new block
 * arrives in English ready to be translated.
 */
class BlockStructure
{
    /**
     * @param  array<array-key, mixed>  $canonical  the default language's blocks
     * @param  array<array-key, mixed>  $locale  the blocks currently in another language
     * @return array<int, mixed> $locale's text realigned to $canonical's structure
     */
    public static function align(array $canonical, array $locale): array
    {
        $remaining = array_values($locale);
        $aligned = [];
        $unmatched = [];

        // First pass: every block takes an identical shape before any block
        // settles for a loose match, so no translation is ever stolen.
        foreach (array_values($canonical) as $index => $block) {
            $match = is_array($block)
                ? self::take($remaining, fn (array $candidate): bool => self::fingerprint($candidate) === self::fingerprint($block))
                : null;

            if ($match === null) {
                $unmatched[] = $index;
            }

            $aligned[$index] = $match;
        }

        // Second pass: same kind of section with slightly different fields
        // (an editor added or removed one input) still keeps its words.
        foreach ($unmatched as $index) {
            $block = array_values($canonical)[$index];
            $match = is_array($block)
                ? self::take($remaining, fn (array $candidate): bool => self::kind($candidate) === self::kind($block))
                : null;

            // A new section: it arrives in the default language, ready to be
            // translated, rather than as an empty hole in every tab.
            $aligned[$index] = $match ?? $block;
        }

        ksort($aligned);

        // Sections the default language no longer has are dropped here too —
        // that is the whole point: delete once, gone in every language.
        return array_values($aligned);
    }

    /**
     * Remove and return the first untouched block matching the test.
     *
     * @param  array<int, mixed>  $blocks
     */
    private static function take(array &$blocks, callable $test): ?array
    {
        foreach ($blocks as $index => $block) {
            if (is_array($block) && $test($block)) {
                unset($blocks[$index]);

                return $block;
            }
        }

        return null;
    }

    /**
     * Shape of a block: type, layout and field list — but none of its words,
     * so translated copies of the same section share a fingerprint.
     */
    private static function fingerprint(array $block): string
    {
        $data = is_array($block['data'] ?? null) ? $block['data'] : [];
        $shape = [];

        foreach (is_array($data['fields'] ?? null) ? $data['fields'] : [] as $field) {
            $shape[] = ($field['type'] ?? '?').':'.(is_countable($field['items'] ?? null) ? count($field['items']) : '-');
        }

        return implode('|', [
            self::kind($block),
            implode(',', $shape),
        ]);
    }

    /** The block type plus layout, which is how an editor names a section. */
    private static function kind(array $block): string
    {
        $data = is_array($block['data'] ?? null) ? $block['data'] : [];

        return ($block['type'] ?? '?').'/'.($data['layout'] ?? '');
    }
}
