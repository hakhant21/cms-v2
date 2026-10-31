<?php

namespace App\Support;

use Illuminate\Support\Str;

/**
 * Read-only view over one section block's payload.
 *
 * Editors build sections from an unordered palette of fields, so the renderer
 * has to answer questions such as "is there a title?", "which field is the
 * media?" and "does this layout still have the content it needs?". Keeping
 * that logic here means the Blade files stay purely presentational.
 */
final class SectionData
{
    public const TEXT_TYPES = ['eyebrow', 'title', 'subtitle', 'paragraph', 'rich_text', 'profile', 'mission', 'vision'];

    public const STATEMENT_LABELS = ['profile' => 'Profile', 'mission' => 'Mission', 'vision' => 'Vision'];

    public const LIST_TYPES = ['list', 'milestones', 'checklist', 'stats', 'pills'];

    /**
     * @param  array<string, mixed>  $data
     */
    private function __construct(private readonly array $data) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function make(array $data): self
    {
        return new self($data);
    }

    /**
     * Every field that still has something to show. List-shaped fields with no
     * rows are dropped so a section never renders an empty card grid.
     *
     * @return array<int, array<string, mixed>>
     */
    public function fields(): array
    {
        $fields = $this->data['fields'] ?? [];

        if (! is_array($fields)) {
            return [];
        }

        return array_values(array_filter($fields, function (mixed $field): bool {
            if (! is_array($field) || ! isset($field['type'])) {
                return false;
            }

            if (in_array($field['type'], ['list', 'milestones', 'checklist', 'stats', 'pills', 'gallery'], true)) {
                return $this->rows($field) !== [];
            }

            return true;
        }));
    }

    /**
     * The first field of one palette type, which is how the signature scenes
     * find their slots.
     *
     * @return array<string, mixed>|null
     */
    public function field(string $type): ?array
    {
        foreach ($this->fields() as $field) {
            if (($field['type'] ?? null) === $type) {
                return $field;
            }
        }

        return null;
    }

    public function value(string $type): ?string
    {
        $value = $this->field($type)['value'] ?? null;

        return is_string($value) && trim($value) !== '' ? $value : null;
    }

    /**
     * Rows of a list-shaped field.
     *
     * @return array<int, array<string, mixed>>
     */
    public function rows(mixed $field): array
    {
        if (! is_array($field)) {
            return [];
        }

        $items = $field['items'] ?? [];

        return is_array($items) ? array_values(array_filter($items, 'is_array')) : [];
    }

    /**
     * The `text` of every row of a checklist-style field.
     *
     * @return array<int, string>
     */
    public function texts(string $type): array
    {
        $texts = [];

        foreach ($this->rows($this->field($type)) as $row) {
            $text = trim((string) ($row['text'] ?? ''));

            if ($text !== '') {
                $texts[] = $text;
            }
        }

        return $texts;
    }

    /**
     * A flat feature list re-grouped under its section headings.
     *
     * The product feature lists arrived from the old site as one list whose
     * group titles are marked by a leading em dash ("— Key Features"). Turning
     * that into numbered cards makes a heading into just another card and reads
     * as a wall of text, so a layout asks for this grouped shape first and only
     * falls back to plain cards when no heading marker is present at all.
     *
     * @return array<int, array{label: string, items: array<int, string>}>|null
     */
    public function groups(mixed $field): ?array
    {
        $rows = $this->rows($field);

        if ($rows === []) {
            return null;
        }

        $groups = [];

        foreach ($rows as $row) {
            $text = trim((string) ($row['text'] ?? ''));

            if ($text === '') {
                continue;
            }

            if (self::isGroupLabel($text)) {
                $groups[] = ['label' => self::stripGroupLabel($text), 'items' => []];

                continue;
            }

            // A bullet written before the first heading cannot be grouped; treat
            // the whole list as ordinary cards rather than invent a heading for
            // it, which is what keeps a normal card grid on this path.
            if ($groups === []) {
                return null;
            }

            $groups[count($groups) - 1]['items'][] = $text;
        }

        return $groups === [] ? null : $groups;
    }

    /** Does this line mark the start of a group ("— Some heading")? */
    private static function isGroupLabel(string $text): bool
    {
        return (bool) preg_match('/^[\x{2013}\x{2014}-]\s+\S/u', $text);
    }

    private static function stripGroupLabel(string $text): string
    {
        return (string) preg_replace('/^[\x{2013}\x{2014}-]\s+/u', '', $text);
    }

    /**
     * Image sources of a gallery field.
     *
     * @return array<int, array{src: string, alt: string}>
     */
    public function gallery(): array
    {
        $images = [];

        foreach ($this->rows($this->field('gallery')) as $row) {
            $src = media_url((string) ($row['src'] ?? ''));

            if ($src !== null) {
                $images[] = ['src' => $src, 'alt' => (string) ($row['alt'] ?? '')];
            }
        }

        return $images;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function textFields(): array
    {
        return array_values(array_filter(
            $this->fields(),
            fn (array $field): bool => in_array($field['type'], self::TEXT_TYPES, true),
        ));
    }

    /**
     * Fields that are not copy and not the inline media slot.
     *
     * @return array<int, array<string, mixed>>
     */
    public function bodyFields(): array
    {
        $media = $this->mediaField();

        return array_values(array_filter(
            $this->fields(),
            fn (array $field): bool => ! in_array($field['type'], self::TEXT_TYPES, true) && $field !== $media,
        ));
    }

    /**
     * @return array<string, mixed>|null
     */
    public function mediaField(): ?array
    {
        foreach ($this->fields() as $field) {
            if (in_array($field['type'], ['image', 'video'], true)) {
                return $field;
            }
        }

        return null;
    }

    /**
     * The three statements of the company profile scene.
     *
     * @return array<string, string|null>
     */
    public function statements(): array
    {
        return [
            'profile' => $this->value('profile'),
            'mission' => $this->value('mission'),
            'vision' => $this->value('vision'),
        ];
    }

    /**
     * Milestones of the farm journey scene, from either field shape.
     *
     * @return array<int, array{year: string, text: string}>
     */
    public function milestones(): array
    {
        $rows = $this->rows($this->field('milestones'));

        if ($rows === []) {
            $rows = $this->rows($this->field('list'));
        }

        return array_values(array_filter(array_map(
            fn (array $row): array => [
                'year' => (string) ($row['year'] ?? $row['title'] ?? ''),
                'text' => (string) ($row['text'] ?? ''),
            ],
            $rows,
        ), fn (array $milestone): bool => $milestone['year'] !== '' || $milestone['text'] !== ''));
    }

    /**
     * Stats shown in the soil infographic.
     *
     * @return array<int, array{value: string, title: string}>
     */
    public function stats(): array
    {
        return array_values(array_filter(array_map(
            fn (array $row): array => [
                'value' => (string) ($row['value'] ?? ''),
                'title' => (string) ($row['title'] ?? ''),
            ],
            $this->rows($this->field('stats')),
        ), fn (array $stat): bool => $stat['value'] !== '' || $stat['title'] !== ''));
    }

    /**
     * The anchor the intro pills link to.
     */
    public function anchor(): string
    {
        $label = $this->value('title') ?? $this->value('eyebrow') ?? '';

        return (string) Str::slug($label);
    }

    /**
     * The requested layout, downgraded when the content a scene needs is
     * missing, so a half-filled section still shows its copy.
     */
    public function layout(): string
    {
        $requested = (string) ($this->data['layout'] ?? 'plain');

        $statementCount = count(array_filter($this->statements(), fn (?string $value): bool => $value !== null));

        return match (true) {
            $requested === 'company_profile' && $statementCount === 0 => 'plain',
            $requested === 'farm_journey' && $this->milestones() === [] => 'plain',
            $requested === 'soil_intro' && $this->value('title') === null && $this->rows($this->field('pills')) === [] => 'plain',
            $requested === 'soil_dimension' && $this->value('title') === null && $this->texts('checklist') === [] && $this->stats() === [] => 'plain',
            default => $requested,
        };
    }

    public function variant(): string
    {
        $variant = (string) ($this->data['variant'] ?? 'structure');

        return in_array($variant, ['structure', 'nutrient', 'biological', 'before_after'], true)
            ? $variant
            : 'structure';
    }

    /** The animated globe caption, when the editor wrote one. */
    public function globeCaption(): ?string
    {
        return $this->value('globe');
    }

    /**
     * The call to action button of a section.
     *
     * @return array{label: string, url: string}|null
     */
    public function button(): ?array
    {
        $field = $this->field('button');

        if ($field === null || trim((string) ($field['value'] ?? '')) === '') {
            return null;
        }

        return [
            'label' => (string) $field['value'],
            'url' => (string) ($field['url'] ?? ''),
        ];
    }
}
