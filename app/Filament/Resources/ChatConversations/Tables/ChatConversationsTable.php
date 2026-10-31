<?php

namespace App\Filament\Resources\ChatConversations\Tables;

use App\Models\ChatConversation;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Enums\FiltersLayout;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

/**
 * The list of conversations, newest activity first: a live chat is only useful
 * if the one that just moved is at the top.
 */
class ChatConversationsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('last_message_at', 'desc')
            ->modifyQueryUsing(fn ($query) => $query->with('lastMessage'))
            ->columns([
                TextColumn::make('name')
                    ->label('Visitor')
                    ->description(fn (ChatConversation $record): string => $record->email ?: 'No email given')
                    ->searchable(['name', 'email'])
                    ->weight(fn (ChatConversation $record): string => $record->agent_unread > 0 ? 'bold' : 'normal'),
                TextColumn::make('agent_unread')
                    ->label('Waiting')
                    ->badge()
                    ->color('danger')
                    ->formatStateUsing(fn (int $state): string => (string) $state)
                    ->placeholder('—')
                    ->tooltip(fn (ChatConversation $record): string => $record->agent_unread > 0
                        ? 'Unread replies from the visitor'
                        : 'All read'),
                TextColumn::make('lastMessage.body')
                    ->label('Last message')
                    ->wrap()
                    ->limit(70)
                    ->placeholder('Nothing written yet'),
                TextColumn::make('status')
                    ->label('State')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => ChatConversation::statusOptions()[$state] ?? $state)
                    ->color(fn (string $state): string => $state === ChatConversation::STATUS_OPEN ? 'success' : 'gray'),
                TextColumn::make('locale')
                    ->label('Language')
                    ->badge()
                    ->color('gray')
                    ->toggleable(),
                TextColumn::make('assignedTo.name')
                    ->label('Assigned')
                    ->placeholder('Nobody')
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('last_message_at')
                    ->label('Activity')
                    ->since()
                    ->sortable(),
            ])
            ->filtersLayout(FiltersLayout::Hidden)
            ->filters([
                SelectFilter::make('status')
                    ->label('State')
                    ->options(ChatConversation::statusOptions()),
            ])
            ->recordActions([
                EditAction::make()
                    ->label('Open')
                    ->tooltip(fn (ChatConversation $record): string => 'Answer this conversation'),
                DeleteAction::make(),
            ])
            ->emptyStateHeading('No conversations yet')
            ->emptyStateDescription('The bubble on the public site opens one as soon as a visitor writes.')
            ->emptyStateIcon('heroicon-o-chat-bubble-left-ellipsis');
    }
}
