<?php

namespace App\Filament\Resources\Redirects\Schemas;

use App\Models\Redirect;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

/**
 * Old address in, new address out.
 *
 * Both sides are written the way an editor thinks about them — a slug or a
 * path, no language prefix — because one row serves all three languages.
 */
class RedirectForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Address')
                    ->columns(2)
                    ->schema([
                        TextInput::make('from_path')
                            ->label('Old address')
                            ->placeholder('blog')
                            ->helperText('Without the language prefix: `blog` covers /blog, /my/blog and /ru/blog.')
                            ->required()
                            ->maxLength(255)
                            ->unique(ignoreRecord: true),
                        TextInput::make('to_path')
                            ->label('New address')
                            ->placeholder('/news')
                            ->helperText('A path on this site, or a full https:// address elsewhere.')
                            ->required()
                            ->maxLength(255),
                        Select::make('status_code')
                            ->label('Type')
                            ->options([
                                Redirect::STATUS_MOVED_PERMANENTLY => '301 — moved for good',
                                Redirect::STATUS_FOUND => '302 — temporary',
                            ])
                            ->default(Redirect::STATUS_MOVED_PERMANENTLY)
                            ->selectablePlaceholder(false)
                            ->required(),
                        Toggle::make('is_active')->label('Live')->default(true),
                    ]),
            ]);
    }
}
