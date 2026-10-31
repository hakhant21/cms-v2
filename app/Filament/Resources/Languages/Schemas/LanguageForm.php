<?php

namespace App\Filament\Resources\Languages\Schemas;

use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

/**
 * Locales are data, not code: adding a row here adds a tab in every content
 * form and an option in the public language switcher.
 */
class LanguageForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Language')
                    ->columns(2)
                    ->schema([
                        TextInput::make('code')
                            ->label('Code')
                            ->required()
                            ->maxLength(8)
                            ->unique(ignoreRecord: true)
                            ->helperText('Two letters such as en, my or ru. It must match the lang folder name.'),
                        TextInput::make('name')
                            ->label('English name')
                            ->required()
                            ->maxLength(60),
                        TextInput::make('native_name')
                            ->label('Name in its own language')
                            ->maxLength(60)
                            ->helperText('Shown in the language switcher, for example မြန်မာ.'),
                        TextInput::make('lang_country')
                            ->label('Regional tag')
                            ->maxLength(12)
                            ->helperText('Optional. Used for hreflang, for example my-MM.'),
                        TextInput::make('direction')
                            ->label('Reading direction')
                            ->default('ltr')
                            ->maxLength(3)
                            ->helperText('ltr for left to right, rtl for right to left.'),
                        TextInput::make('sort_order')
                            ->label('Order')
                            ->numeric()
                            ->default(0)
                            ->helperText('Lower numbers come first.'),
                    ]),
                Section::make('Availability')
                    ->columns(2)
                    ->schema([
                        Toggle::make('is_default')
                            ->label('Default language')
                            ->helperText('Content falls back to this language when a translation is missing.')
                            ->inline(false),
                        Toggle::make('is_active')
                            ->label('Visible on the site')
                            ->default(true)
                            ->helperText('Turn off to hide the language without deleting its content.')
                            ->inline(false),
                    ]),
            ]);
    }
}
