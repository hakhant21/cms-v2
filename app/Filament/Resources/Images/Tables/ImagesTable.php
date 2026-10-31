<?php

namespace App\Filament\Resources\Images\Tables;

use App\Models\Image;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class ImagesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                ImageColumn::make('path')
                    ->label('Image')
                    ->disk('public')
                    ->height(56),
                TextColumn::make('alt')
                    ->label('Alt text')
                    ->searchable()
                    ->wrap(),
                TextColumn::make('dimensions')
                    ->label('Size')
                    ->state(fn (Image $record): string => $record->width !== null && $record->height !== null
                        ? $record->width.' × '.$record->height
                        : '—')
                    ->toggleable(),
                TextColumn::make('created_at')->label('Uploaded')->since(),
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
