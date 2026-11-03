<?php

namespace App\Support\Translation;

use App\Contracts\Translator;
use App\Support\BlockStructure;
use App\Support\SectionData;

/**
 * Turns one language's copy of a page into another's.
 *
 * Only words travel: titles, summaries, the text of every section field, the
 * captions on list rows, and the SEO title and description. Layout, colours,
 * images, links and block order are structure, not language, so they are
 * copied across untouched rather than translated.
 */
final class PageTranslator
{
    /** Keys outside a section that hold a sentence rather than a setting. */
    private const PROSE_KEYS = [
        'title',
        'subtitle',
        'heading',
        'intro',
        'text',
        'description',
        'caption',
        'button_label',
    ];

    public function __construct(private readonly Translator $translator) {}

    /** Whether the underlying translation service can run at all. */
    public function isConfigured(): bool
    {
        return $this->translator->isConfigured();
    }

    /**
     * @param  array<string, mixed>  $source  the language being translated from
     * @param  array<string, mixed>  $target  what the destination already holds
     * @return array{state: array<string, mixed>, translated: int}
     *
     * @throws TranslationFailed
     */
    public function translatePage(array $source, array $target, string $from, string $to, bool $overwrite = false): array
    {
        $texts = $this->texts($source);

        if ($texts === []) {
            return ['state' => $target, 'translated' => 0];
        }

        $values = array_values($this->translator->translate($texts, $from, $to));

        $map = [];

        foreach (array_keys($texts) as $index => $path) {
            $map[$path] = $values[$index] ?? $texts[$path];
        }

        $state = $target;

        foreach (['title', 'excerpt'] as $key) {
            if (isset($map[$key]) && ($overwrite || blank($target[$key] ?? null))) {
                $state[$key] = $map[$key];
            }
        }

        foreach (['title', 'description'] as $key) {
            $path = 'seo.'.$key;

            if (isset($map[$path]) && ($overwrite || blank($target['seo'][$key] ?? null))) {
                $state['seo'][$key] = $map[$path];
            }
        }

        $state['content_blocks'] = $this->blocks($source, $target, $map, $overwrite);

        return ['state' => array_filter($state, fn (mixed $value): bool => $value !== null), 'translated' => count($map)];
    }

    /**
     * The destination's sections, with the translated words written into them.
     *
     * When the destination has no sections yet the source layout is copied and
     * filled in, which is the common case: a page written in English and then
     * translated into Burmese for the first time.
     *
     * Once both sides have sections, an overwrite refits the destination to
     * the source's structure before the words go in. The two shapes drift —
     * the job writes rows directly, bypassing the editor's structure sync, so
     * a field added in the source never reaches the other languages. Refitting
     * keeps whatever the destination already holds where the fields line up
     * and takes the rest from the source. Skipping drifted sections instead
     * silently threw the whole page's translation away while still reporting
     * every string as translated.
     *
     * Without an overwrite the destination's sections are left exactly as
     * they are: the editor asked to fill blanks, not to reshape the page.
     *
     * @param  array<string, mixed>  $source
     * @param  array<string, mixed>  $target
     * @param  array<string, string>  $map
     * @return array<int, array<string, mixed>>
     */
    private function blocks(array $source, array $target, array $map, bool $overwrite): array
    {
        $sourceBlocks = $this->blockList($source);
        $targetBlocks = $this->blockList($target);

        if ($sourceBlocks === []) {
            return $targetBlocks;
        }

        if ($targetBlocks === []) {
            $copy = [];

            foreach ($sourceBlocks as $index => $block) {
                $copy[] = $this->writeTexts($block, $map, "blocks.{$index}.");
            }

            return $copy;
        }

        if (! $overwrite) {
            return $targetBlocks;
        }

        $aligned = BlockStructure::align($sourceBlocks, $targetBlocks);
        $rewritten = [];

        foreach ($aligned as $index => $block) {
            $refit = $this->refitFields($sourceBlocks[$index] ?? [], is_array($block) ? $block : []);
            $rewritten[] = $this->writeTexts($refit, $map, "blocks.{$index}.");
        }

        return $rewritten;
    }

    /**
     * One destination block wearing the source block's field list.
     *
     * Fields are matched by type, the way BlockStructure matches blocks: a
     * field that lines up keeps everything the destination holds (its words,
     * and settings such as image ids that never travel through the
     * translation), a field the destination lacks is taken from the source,
     * and a field the source no longer has is dropped. The result has the
     * source's keys and order, so the translated values — keyed by the
     * source's paths — always land.
     *
     * @param  array<string, mixed>  $sourceBlock
     * @param  array<string, mixed>  $targetBlock
     * @return array<string, mixed>
     */
    private function refitFields(array $sourceBlock, array $targetBlock): array
    {
        $sourceFields = $sourceBlock['data']['fields'] ?? null;

        if (! is_array($sourceFields)) {
            return $targetBlock;
        }

        $targetFields = is_array($targetBlock['data']['fields'] ?? null) ? $targetBlock['data']['fields'] : [];
        $fields = [];
        $claimed = [];

        foreach ($sourceFields as $key => $field) {
            $type = is_array($field) ? ($field['type'] ?? null) : null;
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

            $fields[$key] = $match ?? $field;
        }

        $block = $targetBlock === [] ? $sourceBlock : $targetBlock;
        $block['type'] = $sourceBlock['type'] ?? $block['type'] ?? null;
        $block['data'] = array_merge(is_array($block['data'] ?? null) ? $block['data'] : [], ['fields' => $fields]);

        return $block;
    }

    /**
     * Every translatable string in one language's copy of a page, keyed by the
     * path it can be written back to.
     *
     * @param  array<string, mixed>  $source
     * @return array<string, string>
     */
    public function texts(array $source): array
    {
        $texts = [];

        foreach (['title', 'excerpt'] as $key) {
            $value = $source[$key] ?? null;

            if (is_string($value) && trim($value) !== '') {
                $texts[$key] = trim($value);
            }
        }

        foreach (['title', 'description'] as $key) {
            $value = $source['seo'][$key] ?? null;

            if (is_string($value) && trim($value) !== '') {
                $texts['seo.'.$key] = trim($value);
            }
        }

        foreach ($this->blockList($source) as $index => $block) {
            foreach ($this->blockTexts($block) as $path => $text) {
                $texts["blocks.{$index}.{$path}"] = $text;
            }
        }

        return $texts;
    }

    /**
     * The strings inside one block.
     *
     * A section is a list of typed fields, so its prose is found by field type;
     * every other block keeps its words in a handful of known keys.
     *
     * @param  array<string, mixed>  $block
     * @return array<string, string>
     */
    private function blockTexts(array $block): array
    {
        $data = is_array($block['data'] ?? null) ? $block['data'] : [];
        $texts = [];

        if (($block['type'] ?? null) === 'section') {
            $fields = is_array($data['fields'] ?? null) ? $data['fields'] : [];

            foreach ($fields as $index => $field) {
                if (! is_array($field)) {
                    continue;
                }

                $type = (string) ($field['type'] ?? '');

                // A button's value is its visible label — prose like the text
                // types — while the url beside it is an address and travels
                // untouched.
                if (in_array($type, SectionData::TEXT_TYPES, true) || $type === 'button') {
                    $value = $field['value'] ?? null;

                    if (is_string($value) && trim($value) !== '') {
                        $texts["data.fields.{$index}.value"] = trim($value);
                    }

                    continue;
                }

                if (in_array($type, SectionData::LIST_TYPES, true)) {
                    $rows = is_array($field['items'] ?? null) ? $field['items'] : [];

                    foreach ($rows as $row => $item) {
                        $text = is_array($item) ? ($item['text'] ?? null) : null;

                        if (is_string($text) && trim($text) !== '') {
                            $texts["data.fields.{$index}.items.{$row}.text"] = trim($text);
                        }
                    }
                }
            }

            return $texts;
        }

        foreach (self::PROSE_KEYS as $key) {
            $value = $data[$key] ?? null;

            if (is_string($value) && trim($value) !== '') {
                $texts["data.{$key}"] = trim($value);
            }
        }

        return $texts;
    }

    /**
     * @param  array<string, mixed>  $block
     * @param  array<string, string>  $map
     * @return array<string, mixed>
     */
    private function writeTexts(array $block, array $map, string $prefix): array
    {
        foreach ($this->blockTexts($block) as $path => $original) {
            $translated = $map[$prefix.$path] ?? null;

            if (is_string($translated) && trim($translated) !== '') {
                $block = $this->set($block, $path, $translated);
            }
        }

        return $block;
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function set(array $data, string $path, string $value): array
    {
        $segments = explode('.', $path);
        $cursor = &$data;

        foreach ($segments as $position => $segment) {
            if ($position === count($segments) - 1) {
                $cursor[$segment] = $value;

                break;
            }

            if (! isset($cursor[$segment]) || ! is_array($cursor[$segment])) {
                return $data;
            }

            $cursor = &$cursor[$segment];
        }

        return $data;
    }

    /**
     * @param  array<string, mixed>  $state
     * @return array<int, array<string, mixed>>
     */
    private function blockList(array $state): array
    {
        $blocks = $state['content_blocks'] ?? [];

        return is_array($blocks) ? array_values(array_filter($blocks, 'is_array')) : [];
    }
}
