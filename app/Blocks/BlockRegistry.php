<?php

namespace App\Blocks;

use App\Blocks\Types\ContactDetails;
use App\Blocks\Types\ContactForm;
use App\Blocks\Types\FaqAccordion;
use App\Blocks\Types\FaqItem;
use App\Blocks\Types\FeedbackCollection;
use App\Blocks\Types\FeedbackProfile;
use App\Blocks\Types\Map;
use App\Blocks\Types\PostCollection;
use App\Blocks\Types\ProductCollection;
use App\Blocks\Types\Section;
use App\Blocks\Types\Video;
use LogicException;

/**
 * The catalogue of available block types.
 *
 * Registration is intentionally one explicit list: adding a block type means
 * adding a class and one line here, and nothing else.
 */
final class BlockRegistry
{
    /**
     * Ordered registration list. The order drives the editor picker.
     *
     * @var array<int, class-string<BlockType>>
     */
    private const TYPES = [
        Section::class,
        ProductCollection::class,
        PostCollection::class,
        FeedbackCollection::class,
        Video::class,
        FaqAccordion::class,
        FaqItem::class,
        FeedbackProfile::class,
        ContactDetails::class,
        ContactForm::class,
        Map::class,
    ];

    /**
     * @var array<string, BlockType>
     */
    private array $types = [];

    /**
     * @param  array<int, class-string<BlockType>>  $types
     */
    public function __construct(array $types = self::TYPES)
    {
        foreach ($types as $class) {
            $type = new $class;

            if (isset($this->types[$type->key()])) {
                throw new LogicException("Duplicate block type [{$type->key()}] registered.");
            }

            $this->types[$type->key()] = $type;
        }
    }

    /**
     * @return array<string, BlockType>
     */
    public function all(): array
    {
        return $this->types;
    }

    /**
     * @return array<string, string>
     */
    public function labels(): array
    {
        return array_map(
            fn (BlockType $type): string => $type->label(),
            $this->types,
        );
    }

    /**
     * Labels for the types editors may add themselves.
     *
     * @return array<string, string>
     */
    public function pickerLabels(): array
    {
        $labels = [];

        foreach ($this->types as $key => $type) {
            if ($type->inPicker()) {
                $labels[$key] = $type->label();
            }
        }

        return $labels;
    }

    public function has(string $type): bool
    {
        return isset($this->types[$type]);
    }

    public function label(string $type): string
    {
        return isset($this->types[$type])
            ? $this->types[$type]->label()
            : str($type)->headline()->toString();
    }

    public function kind(string $type): string
    {
        return isset($this->types[$type]) ? $this->types[$type]->kind() : 'static';
    }

    /**
     * @return array<int, mixed>
     */
    public function fields(string $type): array
    {
        return isset($this->types[$type]) ? $this->types[$type]->fields() : [];
    }

    /**
     * @return array<string, mixed>
     */
    public function defaults(string $type): array
    {
        return isset($this->types[$type]) ? $this->types[$type]->defaults() : [];
    }
}
