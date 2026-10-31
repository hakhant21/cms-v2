<?php

namespace App\Filament\Widgets;

use App\Filament\Resources\ContactMessages\ContactMessageResource;
use App\Models\ContactMessage;
use Filament\Actions\Action;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;

/** The newest enquiries, so the inbox can be cleared without leaving the dashboard. */
class LatestMessages extends TableWidget
{
    protected static ?int $sort = 2;

    protected int|string|array $columnSpan = 1;

    public function table(Table $table): Table
    {
        return $table
            ->query(ContactMessage::query()->latest())
            ->heading('Latest messages')
            ->description('The five most recent enquiries.')
            ->columns([
                TextColumn::make('name')
                    ->label('From')
                    ->description(fn (ContactMessage $record): string => (string) $record->subject)
                    ->limit(24),
                TextColumn::make('status')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => ContactMessage::statusOptions()[$state] ?? $state)
                    ->color(fn (string $state): string => $state === ContactMessage::STATUS_NEW ? 'warning' : 'gray'),
                TextColumn::make('created_at')->label('Received')->since(),
            ])
            ->recordActions([
                Action::make('open')
                    ->url(fn (ContactMessage $record): string => ContactMessageResource::getUrl('edit', ['record' => $record])),
            ])
            ->paginated(false)
            ->defaultPaginationPageOption(5);
    }
}
