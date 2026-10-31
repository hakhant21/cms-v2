<?php

namespace App\Blocks\Types;

use App\Blocks\AbstractBlockType;
use Filament\Forms\Components\TextInput;

/** Gathers FAQ pages into an accordion, optionally filtered by context. */
final class FaqAccordion extends AbstractBlockType
{
    public function key(): string
    {
        return 'faq_accordion';
    }

    public function label(): string
    {
        return 'FAQ Accordion';
    }

    public function kind(): string
    {
        return 'collection';
    }

    /**
     * @return array<int, mixed>
     */
    public function fields(): array
    {
        return [
            TextInput::make('title')->label('Section Title')->placeholder('Heading above the questions'),
            TextInput::make('context')
                ->label('FAQ Context')
                ->placeholder('Optional context used to find relevant questions')
                ->helperText('Describe the topic this FAQ block should cover.'),
        ];
    }
}
