<?php

namespace App\Blocks\Types;

use App\Blocks\AbstractBlockType;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Utilities\Get;

/** Gathers farmer feedback pages into a testimonial carousel or grid. */
final class FeedbackCollection extends AbstractBlockType
{
    public function key(): string
    {
        return 'feedback_collection';
    }

    public function label(): string
    {
        return 'Feedback Collection';
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
            TextInput::make('title')->label('Section Title')->placeholder('Heading above the feedback'),
            TextInput::make('limit')
                ->label('Number of Testimonials')
                ->helperText('Maximum number of testimonials to display.')
                ->numeric()
                ->default(6)
                ->minValue(1)
                ->maxValue(24),
            Select::make('display')
                ->label('Display')
                ->options([
                    'carousel' => 'Carousel — cards slide sideways on their own',
                    'grid' => 'Grid — every card visible at once',
                ])
                ->default('carousel')
                ->selectablePlaceholder(false)
                ->live(),
            TextInput::make('per_view')
                ->label('Cards on screen')
                ->helperText('Two side by side reads well; one is used on phones.')
                ->numeric()
                ->default(2)
                ->minValue(1)
                ->maxValue(3)
                ->visible(fn (Get $get): bool => $get('display') !== 'grid'),
            TextInput::make('interval')
                ->label('Seconds between slides')
                ->helperText('How long each pair stays before the next one slides in.')
                ->numeric()
                ->default(2)
                ->minValue(1)
                ->maxValue(20)
                ->visible(fn (Get $get): bool => $get('display') !== 'grid'),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function defaults(): array
    {
        return [
            'title' => 'Client Feedback',
            'limit' => 6,
            'display' => 'carousel',
            'per_view' => 2,
            'interval' => 2,
        ];
    }
}
