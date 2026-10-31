<?php

namespace App\Filament\Resources\ChatConversations\Pages;

use App\Filament\Resources\ChatConversations\ChatConversationResource;
use App\Models\ChatConversation;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

/**
 * Answering a conversation.
 *
 * Opening it marks the visitor's messages as read — that is the whole point of
 * coming here — and the transcript itself is live, so there is nothing for the
 * Save button to do. The page exists to hold the record, the header actions and
 * the embedded chat component.
 */
class ManageChatConversation extends EditRecord
{
    protected static string $resource = ChatConversationResource::class;

    protected static ?string $title = 'Conversation';

    public function mount(int|string $record): void
    {
        parent::mount($record);

        if ($this->getRecord() instanceof ChatConversation) {
            $this->getRecord()->markAgentsRead();
        }
    }

    public function getSubheading(): ?string
    {
        $conversation = $this->getRecord();

        if (! $conversation instanceof ChatConversation) {
            return null;
        }

        return $conversation->status === ChatConversation::STATUS_OPEN
            ? 'Open · '.$conversation->messages()->count().' messages'
            : 'Closed · '.$conversation->messages()->count().' messages';
    }

    /**
     * The transcript writes its own messages, so a Save action would only
     * promise something this page never does.
     *
     * @return array<mixed>
     */
    protected function getFormActions(): array
    {
        return [];
    }

    protected function getHeaderActions(): array
    {
        return [
            // A conversation somebody has taken is a conversation the rest of
            // the team can stop re-reading. It is the only use of the assignee
            // column, and it is deliberately not automatic: nobody is obliged to
            // answer a chat they did not open.
            Action::make('assign')
                ->label('Assign to me')
                ->icon('heroicon-o-user')
                ->color('gray')
                ->visible(fn (ChatConversation $record): bool => $record->assigned_user_id === null)
                ->action(function (ChatConversation $record): void {
                    $record->forceFill(['assigned_user_id' => auth()->id()])->save();
                }),
            DeleteAction::make()
                ->label('Delete conversation')
                ->modalHeading(fn (ChatConversation $record): string => 'Delete the chat with '.$record->displayName().'?')
                ->modalDescription('The whole transcript goes with it. There is no undo.')
                ->modalSubmitActionLabel('Delete'),
        ];
    }
}
