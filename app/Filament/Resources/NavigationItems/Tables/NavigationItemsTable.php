<?php

namespace App\Filament\Resources\NavigationItems\Tables;

use App\Filament\Resources\NavigationItems\Schemas\NavigationItemForm;
use App\Models\NavigationItem;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Enums\FiltersLayout;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class NavigationItemsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('sort_order')
            ->reorderable('sort_order')
            ->columns([
                TextColumn::make('label')
                    ->label('Label')
                    ->state(fn (NavigationItem $record): string => $record->localizedLabel())
                    // The visible label is a state column, so the search has to
                    // reach the translation rows itself; matching the raw URL as
                    // well lets an editor find a link by where it points.
                    ->searchable(query: function (Builder $query, string $search): Builder {
                        $term = '%'.$search.'%';

                        return $query->where(function (Builder $inner) use ($term): void {
                            $inner
                                ->whereHas('translations', fn (Builder $labels): Builder => $labels->where('label', 'like', $term))
                                ->orWhere('url', 'like', $term);
                        });
                    }),
                TextColumn::make('placement')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => NavigationItemForm::placements()[$state] ?? $state),
                TextColumn::make('target')
                    ->label('Points to')
                    ->state(fn (NavigationItem $record): string => $record->href()),
                TextColumn::make('parent_label')
                    ->label('Sits under')
                    ->state(fn (NavigationItem $record): string => $record->parent?->localizedLabel() ?? '—')
                    ->toggleable(),
                IconColumn::make('is_active')->label('Visible')->boolean(),
                TextColumn::make('sort_order')->label('Position')->toggleable(),
            ])
            ->filtersLayout(FiltersLayout::Hidden)
            ->filters([
                SelectFilter::make('placement')
                    ->label('Menu')
                    ->options(NavigationItemForm::placements()),
                TernaryFilter::make('is_active')->label('Visible'),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
