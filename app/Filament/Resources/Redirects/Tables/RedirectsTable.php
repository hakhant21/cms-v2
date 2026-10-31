<?php

namespace App\Filament\Resources\Redirects\Tables;

use App\Models\Redirect;
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

class RedirectsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('from_path')
            ->columns([
                TextColumn::make('from_path')
                    ->label('Old address')
                    ->formatStateUsing(fn (string $state): string => '/'.$state)
                    ->searchable()
                    ->sortable(),
                TextColumn::make('to_path')
                    ->label('Goes to')
                    ->searchable(),
                TextColumn::make('status_code')
                    ->label('Type')
                    ->badge(),
                IconColumn::make('is_active')->label('Live')->boolean(),
                TextColumn::make('hits')
                    ->label('Arrivals')
                    ->numeric()
                    ->sortable(),
            ])
            ->filtersLayout(FiltersLayout::Hidden)
            ->filters([
                SelectFilter::make('status_code')
                    ->label('Type')
                    ->options([
                        Redirect::STATUS_MOVED_PERMANENTLY => '301 — permanent',
                        Redirect::STATUS_FOUND => '302 — temporary',
                    ]),
                TernaryFilter::make('is_active')->label('Live'),
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
