<?php

namespace App\Blocks\Types;

use App\Blocks\AbstractBlockType;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\TextInput;

/**
 * A single question/answer pair. FAQs are pages of kind `faq` carrying this
 * block; the FAQ Accordion block gathers them, optionally filtered by context.
 */
final class FaqItem extends AbstractBlockType
{
    public function key(): string
    {
        return 'faq_item';
    }

    public function label(): string
    {
        return 'FAQ Item';
    }

    /**
     * @return array<int, mixed>
     */
    public function fields(): array
    {
        return [
            TextInput::make('question')
                ->label('Question')
                ->required()
                ->maxLength(255)
                ->columnSpanFull(),
            RichEditor::make('answer')
                ->label('Answer')
                ->required()
                ->columnSpanFull(),
            TextInput::make('context')
                ->label('Context')
                ->placeholder('test-results')
                ->helperText('Only FAQ accordion blocks with the same context show this question.')
                ->maxLength(100),
        ];
    }
}
