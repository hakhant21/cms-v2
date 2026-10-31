<?php

namespace App\Filament\Resources\ChatConversations\Pages;

use App\Filament\Resources\ChatConversations\ChatConversationResource;
use Filament\Resources\Pages\ListRecords;

/**
 * The inbox list. There is no "new conversation" button: chats are opened by
 * visitors on the public site, and an agent writing one by hand would be a
 * strange way to start a conversation.
 */
class ListChatConversations extends ListRecords
{
    protected static string $resource = ChatConversationResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }
}
