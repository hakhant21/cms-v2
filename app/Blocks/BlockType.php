<?php

namespace App\Blocks;

/**
 * Contract for one entry in the page-block library.
 *
 * A block type owns its editor-facing label, its form fields and the defaults
 * used when a new block is created. Adding a block type means adding one class
 * here and registering it in {@see BlockRegistry}.
 */
interface BlockType
{
    /** Stable machine key stored in the block's `type`. */
    public function key(): string;

    /** Label shown to editors. */
    public function label(): string;

    /** Either `static` (hand-written content) or `collection` (reads pages). */
    public function kind(): string;

    /**
     * Filament form fields stored inside the block's `data` state path.
     *
     * @return array<int, mixed>
     */
    public function fields(): array;

    /**
     * Default content of a newly created block.
     *
     * @return array<string, mixed>
     */
    public function defaults(): array;

    /** Whether the type is offered in the block picker. */
    public function inPicker(): bool;
}
