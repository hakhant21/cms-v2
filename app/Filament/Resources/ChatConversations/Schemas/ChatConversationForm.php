<?php

namespace App\Filament\Resources\ChatConversations\Schemas;

use Carbon\Carbon;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Pages\EditRecord;
use Filament\Schemas\Components\Livewire;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

/**
 * What an agent sees when a conversation is opened.
 *
 * The four facts about the visitor are read-only on purpose: they came from the
 * public site and editing them would rewrite what the visitor said about
 * themselves. The transcript itself is a Livewire island rather than form
 * fields, because it has to receive new messages while the page stands still.
 */
class ChatConversationForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Visitor')
                    ->columns(2)
                    ->schema([
                        TextInput::make('name')
                            ->label('Name')
                            ->disabled()
                            ->dehydrated(false),
                        TextInput::make('email')
                            ->label('Email')
                            ->placeholder('None given')
                            ->disabled()
                            ->dehydrated(false),
                        TextInput::make('locale')
                            ->label('Language')
                            ->disabled()
                            ->dehydrated(false),
                        TextInput::make('last_message_at')
                            ->label('Last message')
                            ->formatStateUsing(fn (mixed $state): string => $state instanceof Carbon
                                ? $state->diffForHumans()
                                : 'No activity yet')
                            ->disabled()
                            ->dehydrated(false),
                    ]),
                Livewire::make(
                    'chat-thread',
                    fn (EditRecord $livewire): array => [
                        'conversationId' => (int) $livewire->getRecord()->getKey(),
                    ],
                ),
            ]);
    }
}
