<?php

namespace App\Blocks\Types;

use App\Blocks\AbstractBlockType;
use Filament\Forms\Components\TextInput;

/** Gathers the published product pages into a card grid. */
final class ProductCollection extends AbstractBlockType
{
    public function key(): string
    {
        return 'product_collection';
    }

    public function label(): string
    {
        return 'Product Collection';
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
            TextInput::make('title')->label('Section Title')->placeholder('Heading above the products'),
            TextInput::make('limit')
                ->label('Number of Products')
                ->helperText('Maximum number of products to display.')
                ->numeric()
                ->default(6)
                ->minValue(1)
                ->maxValue(24),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function defaults(): array
    {
        return ['title' => 'Our Products', 'limit' => 6];
    }
}
