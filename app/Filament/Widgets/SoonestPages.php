<?php

namespace App\Filament\Widgets;

use App\Filament\Resources\Pages\PageResource;
use App\Models\Page;
use Filament\Actions\Action;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;

/** Recently edited pages, matching how an editor actually works. */
class SoonestPages extends TableWidget
{
    protected static ?int $sort = 3;

    protected int|string|array $columnSpan = 1;

    public function table(Table $table): Table
    {
        return $table
            ->query(Page::query()->with('translations')->latest('updated_at'))
            ->heading('Recently edited')
            ->description('Jump back into what you were writing.')
            // The dashboard is a summary, not a second page list, so it shows
            // a handful and links out for the rest.
            ->defaultPaginationPageOption(5)
            ->paginated([5])
            ->columns([
                TextColumn::make('title')
                    ->label('Page')
                    ->state(fn (Page $record): string => $record->localizedTitle())
                    ->description(fn (Page $record): string => $record->path()),
                TextColumn::make('status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        Page::STATUS_PUBLISHED => 'success',
                        Page::STATUS_ARCHIVED => 'gray',
                        default => 'warning',
                    }),
            ])
            ->recordActions([
                Action::make('edit')
                    ->url(fn (Page $record): string => PageResource::getUrl('edit', ['record' => $record])),
            ])
            ->headerActions([
                Action::make('all')
                    ->label('All pages')
                    ->link()
                    ->url(PageResource::getUrl('index')),
            ]);
    }
}
