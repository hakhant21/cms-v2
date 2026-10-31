<?php

namespace App\Filament\Resources\ContactMessages\Tables;

use App\Models\ContactMessage;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Enums\FiltersLayout;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class ContactMessagesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('name')
                    ->label('From')
                    ->description(fn (ContactMessage $record): string => (string) $record->email)
                    ->searchable(['name', 'email'])
                    ->weight(fn (ContactMessage $record): string => $record->status === ContactMessage::STATUS_NEW ? 'bold' : 'normal'),
                TextColumn::make('subject')
                    ->label('Subject')
                    ->placeholder('No subject')
                    ->limit(50)
                    ->searchable(),
                TextColumn::make('message')
                    ->label('Message')
                    ->limit(70)
                    ->wrap()
                    ->toggleable(),
                TextColumn::make('locale')
                    ->label('Language')
                    ->badge()
                    ->color('gray')
                    ->toggleable(),
                TextColumn::make('status')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => ContactMessage::statusOptions()[$state] ?? $state)
                    ->color(fn (string $state): string => match ($state) {
                        ContactMessage::STATUS_NEW => 'warning',
                        ContactMessage::STATUS_REPLIED => 'success',
                        ContactMessage::STATUS_ARCHIVED => 'gray',
                        default => 'info',
                    }),
                TextColumn::make('created_at')
                    ->label('Received')
                    ->since()
                    ->sortable(),
            ])
            ->filtersLayout(FiltersLayout::Hidden)
            ->filters([
                SelectFilter::make('status')
                    ->label('Status')
                    ->options(ContactMessage::statusOptions()),
            ])
            ->recordActions([
                EditAction::make()
                    ->label('Open'),
                Action::make('markReplied')
                    ->label('Mark replied')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->visible(fn (ContactMessage $record): bool => $record->status !== ContactMessage::STATUS_REPLIED)
                    ->action(fn (ContactMessage $record) => $record->markReplied()),
                Action::make('archive')
                    ->label('Archive')
                    ->icon('heroicon-o-archive-box')
                    ->visible(fn (ContactMessage $record): bool => $record->status !== ContactMessage::STATUS_ARCHIVED)
                    ->requiresConfirmation()
                    ->action(fn (ContactMessage $record) => $record->forceFill(['status' => ContactMessage::STATUS_ARCHIVED])->save()),
                DeleteAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
