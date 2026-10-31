<?php

namespace App\Blocks\Types;

use App\Blocks\AbstractBlockType;
use App\Support\GoogleMap;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;

/**
 * The office map.
 *
 * The location normally comes from the site settings; both fields here are
 * optional overrides for a page that should point somewhere else — a branch
 * office or a farm site.
 */
final class Map extends AbstractBlockType
{
    public function key(): string
    {
        return 'map';
    }

    public function label(): string
    {
        return 'Map';
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
            TextInput::make('title')->label('Section Title')->placeholder('Heading above this section'),
            TextInput::make('query')
                ->label('Location for this map')
                ->placeholder('Leave empty to use the company location')
                ->helperText('Latitude, longitude — or an address.'),
            Select::make('zoom')
                ->label('Zoom')
                ->placeholder('Use the site setting')
                ->options([
                    GoogleMap::MIN_ZOOM => 'Whole country',
                    10 => 'Region',
                    12 => 'District',
                    14 => 'Neighbourhood',
                    16 => 'Street',
                    18 => 'Building',
                ])
                ->live(),
        ];
    }
}
