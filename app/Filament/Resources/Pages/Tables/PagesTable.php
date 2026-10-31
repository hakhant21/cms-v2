<?php

namespace App\Filament\Resources\Pages\Tables;

use App\Filament\Resources\Pages\Schemas\PageForm;
use App\Models\Page;
use App\Support\PageDuplicator;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\BulkAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ForceDeleteAction;
use Filament\Actions\ForceDeleteBulkAction;
use Filament\Actions\RestoreAction;
use Filament\Actions\RestoreBulkAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Enums\FiltersLayout;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

class PagesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('sort_order')
            ->columns([
                TextColumn::make('title')
                    ->label('Title')
                    ->state(fn (Page $record): string => $record->localizedTitle())
                    ->searchable(query: fn (Builder $query, string $search): Builder => $query
                        ->whereHas('translations', fn (Builder $inner): Builder => $inner->where('title', 'like', "%{$search}%")))
                    ->grow()
                    ->wrap(),
                TextColumn::make('kind')
                    ->label('Type')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => PageForm::kinds()[$state] ?? $state)
                    ->color('gray')
                    // Editors scan for a page by title; the type is one click
                    // away in the column toggle and filter, and on a narrow
                    // screen it squeezed titles to one word per line.
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('status')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => PageForm::statuses()[$state] ?? $state)
                    ->color(fn (string $state): string => match ($state) {
                        Page::STATUS_PUBLISHED => 'success',
                        Page::STATUS_ARCHIVED => 'warning',
                        default => 'gray',
                    }),
                TextColumn::make('slug')
                    ->label('Address')
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('updated_at')
                    ->label('Last edited')
                    ->since()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            // Only choice filters here, so the toolbar shows them as selects
            // next to the search box and the panel button is redundant.
            ->filtersLayout(FiltersLayout::Hidden)
            ->filters([
                SelectFilter::make('kind')
                    ->label('Type')
                    ->options(PageForm::kinds()),
                SelectFilter::make('status')
                    ->options(PageForm::statuses()),
                TrashedFilter::make(),
            ])
            ->recordActions([
                // Two visible actions and a menu: the five flat buttons this
                // replaced claimed more width than the title column on a
                // narrow screen.
                EditAction::make(),
                Action::make('preview')
                    ->label('View')
                    ->icon('heroicon-m-arrow-top-right-on-square')
                    ->url(fn (Page $record): string => $record->status === Page::STATUS_PUBLISHED
                        ? url($record->path())
                        : route('preview', ['page' => $record->getKey()]))
                    ->openUrlInNewTab(),
                ActionGroup::make([
                    Action::make('duplicate')
                        ->label('Duplicate')
                        ->icon('heroicon-m-document-duplicate')
                        ->requiresConfirmation()
                        ->modalDescription('Creates a draft copy with the same sections and translations.')
                        ->action(fn (Page $record): Page => PageDuplicator::duplicate($record))
                        ->successNotificationTitle('Draft copy created'),
                    DeleteAction::make(),
                    RestoreAction::make(),
                    ForceDeleteAction::make(),
                ])->label('More'),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    BulkAction::make('publish')
                        ->label('Publish')
                        ->icon('heroicon-m-check-circle')
                        ->requiresConfirmation()
                        ->action(fn (Collection $records) => $records->each->update([
                            'status' => Page::STATUS_PUBLISHED,
                            'published_at' => now(),
                        ]))
                        ->deselectRecordsAfterCompletion(),
                    BulkAction::make('archive')
                        ->label('Archive')
                        ->icon('heroicon-m-archive-box')
                        ->requiresConfirmation()
                        ->action(fn (Collection $records) => $records->each->update(['status' => Page::STATUS_ARCHIVED]))
                        ->deselectRecordsAfterCompletion(),
                    DeleteBulkAction::make(),
                    RestoreBulkAction::make(),
                    ForceDeleteBulkAction::make(),
                ]),
            ]);
    }
}
