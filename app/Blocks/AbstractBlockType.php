<?php

namespace App\Blocks;

/**
 * Base class for block types. Only the key and label are required; a type is
 * static content, included in the picker and has no defaults unless it
 * overrides the corresponding method.
 */
abstract class AbstractBlockType implements BlockType
{
    public function kind(): string
    {
        return 'static';
    }

    /**
     * @return array<string, mixed>
     */
    public function defaults(): array
    {
        return [];
    }

    public function inPicker(): bool
    {
        return true;
    }
}
