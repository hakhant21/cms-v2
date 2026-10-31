<?php

namespace App\Livewire;

use App\Models\ChatConversation;
use App\Models\ChatMessage;
use App\Support\Chat;
use Livewire\Attributes\Locked;
use Livewire\Attributes\On;
use Livewire\Component;

/**
 * The conversation as the team sees it, embedded in the admin.
 *
 * It is a plain Livewire component rather than a Filament form so a reply can
 * be one keystroke away: the resource handles finding a conversation, this one
 * handles the exchange. Everything is read from the database on each render and
 * the conversation id is locked, so a browser cannot point the panel at a
 * different chat than the page it was opened from.
 */
class ChatThread extends Component
{
    #[Locked]
    public int $conversationId = 0;

    public string $reply = '';

    private ?ChatConversation $conversation = null;

    public function mount(int $conversationId = 0): void
    {
        $this->conversationId = $conversationId;

        // Opening the thread is reading it.
        $this->thread()?->markAgentsRead();
    }

    /**
     * A message arrived somewhere on the team's channel. Only the thread that
     * owns the conversation redraws; the payload travels in the clear, so the
     * id inside it is compared rather than trusted.
     */
    #[On('echo:chat.agents:MessageSent')]
    public function incoming(array $payload = []): void
    {
        if ((int) ($payload['conversation'] ?? 0) !== $this->conversationId) {
            return;
        }

        $this->refresh();
    }

    public function refresh(): void
    {
        $this->conversation = null;

        $this->thread()?->markAgentsRead();
    }

    public function send(): void
    {
        $conversation = $this->thread();

        if ($conversation === null) {
            return;
        }

        $this->validate([
            'reply' => ['required', 'string', 'min:1', 'max:'.Chat::MAX_BODY_LENGTH],
        ]);

        // Replying to a chat the visitor has since closed brings it back — an
        // answer is always worth sending.
        if (! $conversation->isOpen()) {
            $conversation->reopen();
        }

        $this->announce($conversation->record(ChatMessage::fromAgent(trim($this->reply), auth()->user())));

        $this->reset('reply');
    }

    public function close(): void
    {
        $conversation = $this->thread();

        if ($conversation === null) {
            return;
        }

        $note = trim((string) $conversation->email) !== ''
            ? __('ui.chat.closed_by_team', ['email' => $conversation->email])
            : __('ui.chat.closed');

        $this->announce($conversation->close($note));
    }

    private function announce(?ChatMessage $message): void
    {
        if ($message !== null) {
            Chat::announce($message);
        }
    }

    /** This conversation, read once per request. */
    private function thread(): ?ChatConversation
    {
        if ($this->conversation === null && $this->conversationId > 0) {
            $this->conversation = ChatConversation::query()->find($this->conversationId);
        }

        return $this->conversation;
    }

    public function render()
    {
        $conversation = $this->thread();

        return view('livewire.chat-thread', [
            'conversation' => $conversation,
            'messages' => $conversation?->transcript() ?? collect(),
            'maxLength' => Chat::MAX_BODY_LENGTH,
        ]);
    }
}
