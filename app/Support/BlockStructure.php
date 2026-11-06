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
 *
 * Structure includes the media: an image is uploaded once and every language
 * shows it, so {@see mirror} copies image paths, galleries, video and button
 * addresses across while the words stay per-language.
 */
class BlockStructure
{
    /** Keys outside a section that hold a sentence rather than a setting. */
    public const PROSE_KEYS = [
        'title',
        'subtitle',
        'heading',
        'intro',
        'text',
        'description',
        'caption',
        'button_label',
        // FAQ pages: the question and its rich answer are the whole block.
        'question',
        'answer',
        // Farmer feedback profiles: every word the card shows.
        'farmer_name',
        'location',
        'quote',
        'crops_grown',
        'crop',
        'crop_variety',
        'acreage',
        'field_challenge',
        'treatment',
        'other_crop_details',
        'previous_year_yield',
        'ruma_field_yield',
    ];

    /**
     * Row lists outside a section whose rows hold prose under a known key
     * (feedback results and benefits). The rows belong to the locale; only
     * their count and order follow the source.
     *
     * @var array<string, string>
     */
    public const ROW_PROSE_KEYS = [
        'proven_results' => 'value',
        'key_benefits' => 'value',
    ];

    /** Field keys that hold an address or a setting, never a sentence. */
    private const STRUCTURAL_KEYS = [
        'image' => ['value'],
        'video' => ['value'],
        'button' => ['url'],
    ];

    /** The same, per row of a list-shaped field. */
    private const ROW_STRUCTURAL_KEYS = [
        'gallery' => ['src'],
        'pills' => ['url'],
        'stats' => ['value'],
        'milestones' => ['year'],
    ];

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
        foreach ($unmatched as $position => $index) {
            $block = array_values($canonical)[$index];
            $match = is_array($block)
                ? self::take($remaining, fn (array $candidate): bool => self::kind($candidate) === self::kind($block))
                : null;

            if ($match !== null) {
                $aligned[$index] = $match;
                unset($unmatched[$position]);
            }
        }

        // Third pass: the layout changed but the fields are the same ones —
        // arrangement is structure, not identity, so the words survive. A
        // brand-new section whose fields happen to look like an existing one
        // never reaches this pass: the second pass already claimed it.
        foreach ($unmatched as $index) {
            $block = array_values($canonical)[$index];
            $match = is_array($block)
                ? self::take($remaining, fn (array $candidate): bool => self::type($candidate) === self::type($block)
                    && self::fieldShape($candidate) === self::fieldShape($block))
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
     * Align, then copy the structure — media included — into every block.
     *
     * This is the full sync: the locale keeps its own words, but its images,
     * galleries, links and layout always match the default language, so an
     * upload or a link change happens once for the whole page.
     *
     * @param  array<array-key, mixed>  $canonical  the default language's blocks
     * @param  array<array-key, mixed>  $locale  the blocks currently in another language
     * @return array<int, mixed>
     */
    public static function mirror(array $canonical, array $locale): array
    {
        $aligned = self::align($canonical, $locale);
        $canonical = array_values($canonical);

        foreach ($aligned as $index => $block) {
            $source = $canonical[$index] ?? null;

            if (is_array($source) && is_array($block)) {
                $aligned[$index] = self::overlay($source, $block);
            }
        }

        return array_values($aligned);
    }

    /**
     * One block wearing the source's structure while keeping its own words.
     *
     * Fields are matched by type — never by key or position, since stored
     * field keys are a mix of integers and uuids. A matched field keeps its
     * prose; its addresses and settings come from the source. A field the
     * target lacks arrives whole from the source, ready to be translated.
     *
     * @param  array<string, mixed>  $source
     * @param  array<string, mixed>  $target
     * @return array<string, mixed>
     */
    public static function overlay(array $source, array $target): array
    {
        $data = is_array($target['data'] ?? null) ? $target['data'] : [];
        $sourceData = is_array($source['data'] ?? null) ? $source['data'] : [];

        if (($source['type'] ?? null) !== 'section') {
            // Outside sections the prose sits under a handful of known keys;
            // everything else (video urls, map settings, ...) is structure.
            foreach ($sourceData as $key => $value) {
                if (in_array($key, self::PROSE_KEYS, true)) {
                    continue;
                }

                // Prose row lists keep the locale's own rows, trimmed to the
                // source's count, with fresh rows arriving ready to translate.
                if (isset(self::ROW_PROSE_KEYS[$key])) {
                    $data[$key] = self::mergeProseRows(
                        is_array($value) ? $value : [],
                        is_array($data[$key] ?? null) ? $data[$key] : [],
                    );

                    continue;
                }

                $data[$key] = $value;
            }

            $source['data'] = $data;

            return $source;
        }

        foreach (['layout', 'variant'] as $key) {
            if (array_key_exists($key, $sourceData)) {
                $data[$key] = $sourceData[$key];
            }
        }

        $data['fields'] = self::overlayFields($sourceData['fields'] ?? null, $data['fields'] ?? null);
        $source['data'] = $data;

        return $source;
    }

    /**
     * @return array<array-key, mixed>
     */
    private static function overlayFields(mixed $sourceFields, mixed $targetFields): array
    {
        if (! is_array($sourceFields)) {
            return is_array($targetFields) ? $targetFields : [];
        }

        $targetFields = is_array($targetFields) ? $targetFields : [];
        $fields = [];
        $claimed = [];

        foreach ($sourceFields as $key => $field) {
            if (! is_array($field)) {
                $fields[$key] = $field;

                continue;
            }

            $type = $field['type'] ?? null;
            $match = null;

            foreach ($targetFields as $targetKey => $candidate) {
                if (! in_array($targetKey, $claimed, true)
                    && is_array($candidate)
                    && ($candidate['type'] ?? null) === $type) {
                    $match = $candidate;
                    $claimed[] = $targetKey;

                    break;
                }
            }

            $fields[$key] = self::overlayField($field, $match ?? $field);
        }

        return $fields;
    }

    /**
     * @param  array<string, mixed>  $source
     * @param  array<string, mixed>  $target
     * @return array<string, mixed>
     */
    private static function overlayField(array $source, array $target): array
    {
        $type = (string) ($source['type'] ?? '');

        if (isset(self::ROW_STRUCTURAL_KEYS[$type])) {
            // The rows carry the target's prose; the field itself takes the
            // source's row list, so added or removed photos follow along.
            $source['items'] = self::overlayRows(
                is_array($source['items'] ?? null) ? $source['items'] : [],
                is_array($target['items'] ?? null) ? $target['items'] : [],
                self::ROW_STRUCTURAL_KEYS[$type],
            );

            return $source;
        }

        if (isset(self::STRUCTURAL_KEYS[$type])) {
            foreach (self::STRUCTURAL_KEYS[$type] as $key) {
                if (array_key_exists($key, $source)) {
                    $target[$key] = $source[$key];
                }
            }

            return self::keepAlt($target, $source);
        }

        // Prose-only lists (items, checklists, callout bullets): the target
        // keeps its translated rows, trimmed to the source's count, and any
        // new row arrives in the source language ready to translate.
        if (is_array($source['items'] ?? null)) {
            $sourceItems = array_values($source['items']);
            $targetItems = array_slice(
                is_array($target['items'] ?? null) ? array_values($target['items']) : [],
                0,
                count($sourceItems),
            );

            for ($index = count($targetItems); $index < count($sourceItems); $index++) {
                $targetItems[] = $sourceItems[$index];
            }

            $target['items'] = $targetItems;
        }

        return $target;
    }

    /**
     * Rows in the source's order and count: each keeps the target's prose,
     * takes the structural keys from the source row, and a brand-new row
     * arrives whole from the source.
     *
     * @param  array<array-key, mixed>  $sourceRows
     * @param  array<array-key, mixed>  $targetRows
     * @param  array<int, string>  $structural
     * @return array<int, mixed>
     */
    private static function overlayRows(array $sourceRows, array $targetRows, array $structural): array
    {
        $sourceRows = array_values(array_filter($sourceRows, 'is_array'));
        $targetRows = array_values(array_filter($targetRows, 'is_array'));
        $rows = [];

        foreach ($sourceRows as $index => $row) {
            $keep = $targetRows[$index] ?? $row;

            foreach ($structural as $key) {
                if (array_key_exists($key, $row)) {
                    $keep[$key] = $row[$key];
                }
            }

            $rows[] = self::keepAlt($keep, $row);
        }

        return $rows;
    }

    /**
     * Alt text is prose: keep the locale's own when it has one, otherwise
     * fall back to the source's so a fresh upload is never unlabelled.
     *
     * @param  array<string, mixed>  $keep
     * @param  array<string, mixed>  $source
     * @return array<string, mixed>
     */
    private static function keepAlt(array $keep, array $source): array
    {
        $alt = $keep['alt'] ?? null;

        if ((! is_string($alt) || trim($alt) === '') && filled($source['alt'] ?? null)) {
            $keep['alt'] = $source['alt'];
        }

        return $keep;
    }

    /**
     * The target's prose rows in the source's count and order: rows beyond
     * the source list are dropped, and rows the source added arrive whole.
     *
     * @param  array<array-key, mixed>  $sourceRows
     * @param  array<array-key, mixed>  $targetRows
     * @return array<int, mixed>
     */
    private static function mergeProseRows(array $sourceRows, array $targetRows): array
    {
        $sourceRows = array_values($sourceRows);
        $rows = array_slice(array_values($targetRows), 0, count($sourceRows));

        for ($index = count($rows); $index < count($sourceRows); $index++) {
            $rows[] = $sourceRows[$index];
        }

        return $rows;
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
        return implode('|', [
            self::kind($block),
            self::fieldShape($block),
        ]);
    }

    /** The field types and row counts, in order — the section's skeleton. */
    private static function fieldShape(array $block): string
    {
        $data = is_array($block['data'] ?? null) ? $block['data'] : [];
        $shape = [];

        foreach (is_array($data['fields'] ?? null) ? $data['fields'] : [] as $field) {
            $shape[] = ($field['type'] ?? '?').':'.(is_countable($field['items'] ?? null) ? count($field['items']) : '-');
        }

        return implode(',', $shape);
    }

    /** The block type plus layout, which is how an editor names a section. */
    private static function kind(array $block): string
    {
        $data = is_array($block['data'] ?? null) ? $block['data'] : [];

        return self::type($block).'/'.($data['layout'] ?? '');
    }

    private static function type(array $block): string
    {
        return (string) ($block['type'] ?? '?');
    }
}
