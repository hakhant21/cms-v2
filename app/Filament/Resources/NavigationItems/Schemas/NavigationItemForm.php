<?php

namespace App\Filament\Resources\NavigationItems\Schemas;

use App\Models\Language;
use App\Models\NavigationItem;
use App\Models\Page;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;

/**
 * A menu link. The target is shared by every language; only the visible label
 * is translated, which is why the labels sit in their own repeater.
 */
class NavigationItemForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Link')
                    ->columns(2)
                    ->schema([
                        Select::make('placement')
                            ->label('Menu')
                            ->options(self::placements())
                            ->default(NavigationItem::PLACEMENT_HEADER)
                            ->required(),
                        Select::make('parent_id')
                            ->label('Sits under')
                            ->relationship('parent', 'id')
                            ->getOptionLabelFromRecordUsing(fn (NavigationItem $record): string => $record->localizedLabel())
                            ->placeholder('Top level')
                            ->searchable()
                            ->preload(),
                        Select::make('target_type')
                            ->label('Links to')
                            ->options([
                                NavigationItem::TARGET_PAGE => 'A page on this site',
                                NavigationItem::TARGET_URL => 'A custom address',
                            ])
                            ->default(NavigationItem::TARGET_PAGE)
                            ->required()
                            ->live(),
                        Select::make('page_id')
                            ->label('Page')
                            ->relationship('page', 'slug')
                            ->getOptionLabelFromRecordUsing(fn (Page $record): string => $record->localizedTitle())
                            ->searchable()
                            ->preload()
                            ->visible(fn (Get $get): bool => $get('target_type') === NavigationItem::TARGET_PAGE),
                        TextInput::make('url')
                            ->label('Address')
                            ->placeholder('/contact')
                            ->maxLength(255)
                            ->visible(fn (Get $get): bool => $get('target_type') === NavigationItem::TARGET_URL),
                        TextInput::make('sort_order')
                            ->label('Position')
                            ->numeric()
                            ->default(0)
                            ->helperText('Lower numbers appear first.'),
                        Toggle::make('is_active')->label('Visible')->default(true),
                        Toggle::make('opens_new_tab')->label('Open in a new tab'),
                    ]),
                Repeater::make('translations')
                    ->relationship()
                    ->label('Labels')
                    ->helperText('The text visitors see, per language.')
                    ->columns(2)
                    ->defaultItems(1)
                    ->itemLabel(fn (array $state): string => self::languageLabel((string) ($state['locale'] ?? '')))
                    ->schema([
                        Select::make('locale')
                            ->label('Language')
                            ->options(self::languages())
                            ->required(),
                        TextInput::make('label')->label('Label')->required()->maxLength(120),
                    ])
                    ->columnSpanFull(),
            ]);
    }

    /**
     * @return array<string, string>
     */
    public static function placements(): array
    {
        return [
            NavigationItem::PLACEMENT_HEADER => 'Header',
            NavigationItem::PLACEMENT_FOOTER => 'Footer',
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function languages(): array
    {
        return Language::query()
            ->active()
            ->get()
            ->mapWithKeys(fn (Language $language): array => [
                $language->code => (string) ($language->native_name ?: $language->name),
            ])
            ->all();
    }

    private static function languageLabel(string $code): string
    {
        return self::languages()[$code] ?? ($code !== '' ? strtoupper($code) : 'New label');
    }
}
