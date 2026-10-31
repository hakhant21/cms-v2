<?php

namespace App\Livewire;

use App\Models\ChatConversation;
use App\Models\ChatMessage;
use App\Support\Chat;
use Illuminate\Support\Facades\Session;
use Livewire\Attributes\On;
use Livewire\Component;

/**
 * The chat bubble in the corner of the public site.
 *
 * Two things shape this component. First, the visitor is a guest: the only
 * credential is the token in their session, so every action re-reads the
 * conversation from the database instead of trusting what the browser carries.
 * Second, delivery is best effort: a broadcast pushes the transcript to the
 * other side immediately, while `wire:poll` on the open panel means a stopped
 * Reverb process costs the instant feeling only, never the message.
 */
class ChatWidget extends Component
{
    public string $token = '';

    public bool $open = false;

    public string $draft = '';

    public string $name = '';

    public string $email = '';

    public ?int $conversationId = null;

    /** Set when the visitor is typing faster than the limiter allows. */
    public bool $rateLimited = false;

    public function mount(): void
    {
        // The channel this browser listens on is decided here and never changes
        // for the lifetime of the component: Livewire only subscribes on mount,
        // so a token minted later would leave the visitor deaf.
        $this->token = Chat::token();

        $conversation = $this->load();

        $this->name = (string) ($conversation?->name ?? Session::get('chat.name', ''));
        $this->email = (string) ($conversation?->email ?? Session::get('chat.email', ''));
    }

    public function toggle(): void
    {
        $this->open = ! $this->open;

        if ($this->open) {
            $this->refresh();
        }
    }

    public function close(): void
    {
        $this->open = false;
    }

    /**
     * The visitor's first line, which doubles as the identity step: a chat the
     * team cannot name or reach is not worth opening.
     */
    public function start(): void
    {
        $this->validate([
            'name' => ['required', 'string', 'min:2', 'max:120'],
            'email' => ['nullable', 'email:rfc', 'max:180'],
            'draft' => ['required', 'string', 'min:1', 'max:'.Chat::MAX_BODY_LENGTH],
        ]);

        $this->rememberIdentity();

        $conversation = $this->load() ?? ChatConversation::create([
            'token' => $this->token,
            'name' => trim($this->name),
            'email' => trim($this->email) ?: null,
            'locale' => app()->getLocale(),
            'status' => ChatConversation::STATUS_OPEN,
        ]);

        $this->conversationId = $conversation->getKey();
        session([Chat::SESSION_CONVERSATION => $conversation->getKey()]);

        $body = trim($this->draft);

        // The greeting goes in first so the transcript reads in the order the
        // two sides actually experienced it, and only when one is written.
        if ($conversation->wasRecentlyCreated && filled(Chat::greeting())) {
            $this->push($conversation, ChatMessage::system((string) Chat::greeting()));
        }

        if ($this->push($conversation, ChatMessage::fromVisitor($body))) {
            $this->reset('draft');
        }
    }

    /**
     * One entry point for the form and for the Enter key: the first message
     * opens a conversation, everything after it goes into the existing one.
     */
    public function submit(): void
    {
        $this->load() === null ? $this->start() : $this->send();
    }

    public function send(): void
    {
        $conversation = $this->load();

        if ($conversation === null) {
            $this->start();

            return;
        }

        $this->validate([
            'draft' => ['required', 'string', 'min:1', 'max:'.Chat::MAX_BODY_LENGTH],
        ]);

        // A closed chat is not a wall: the visitor writing again simply reopens
        // it, which is what every other help bubble on the web does.
        if (! $conversation->isOpen()) {
            $conversation->reopen();
        }

        $this->rememberIdentity();

        $body = trim($this->draft);

        if (trim($this->name) !== '' && trim($this->name) !== $conversation->name) {
            $conversation->update(['name' => trim($this->name)]);
        }

        if ($this->push($conversation, ChatMessage::fromVisitor($body))) {
            $this->reset('draft');
        }
    }

    /** Bring the local copy up to date — the poll and the socket both land here. */
    public function refresh(): void
    {
        $this->conversationId = $this->load()?->getKey();

        // Only read on the visitor's behalf while the panel is actually open;
        // otherwise the dot on the closed bubble would clear itself.
        if ($this->open) {
            $this->load()?->markVisitorRead();
        }
    }

    /**
     * Something arrived over the socket. The payload travels on a public
     * channel, so the token inside it is checked rather than trusted.
     */
    #[On('echo:chat.conversation.{token}:MessageSent')]
    public function onMessage(array $payload = []): void
    {
        if (! Chat::ownsToken($this->token, $payload['token'] ?? null)) {
            return;
        }

        $this->refresh();
    }

    /**
     * Store one line and tell both sides about it.
     *
     * The draft is left alone on purpose: the caller that opens a chat pushes
     * two lines (the greeting, then the visitor's question) and would empty the
     * second one with the first. A refused message also stays on screen.
     *
     * @return bool whether the message was written
     */
    private function push(ChatConversation $conversation, ChatMessage $message): bool
    {
        if (Chat::throttled($this->token)) {
            $this->rateLimited = true;

            return false;
        }

        $this->rateLimited = false;

        $conversation->record($message);
        $this->conversationId = $conversation->getKey();

        Chat::announce($message);

        return true;
    }

    private function rememberIdentity(): void
    {
        Session::put('chat.name', trim($this->name));
        Session::put('chat.email', trim($this->email));
    }

    /** The conversation this browser owns, read from the database every time. */
    private function load(): ?ChatConversation
    {
        $conversation = Chat::currentConversation();

        if ($conversation !== null) {
            $this->conversationId = $conversation->getKey();
            session([Chat::SESSION_CONVERSATION => $conversation->getKey()]);
        }

        return $conversation;
    }

    public function render()
    {
        $conversation = $this->load();

        return view('livewire.chat-widget', [
            'conversation' => $conversation,
            'messages' => $conversation?->transcript() ?? collect(),
            'unread' => $conversation?->visitor_unread ?? 0,
            'maxLength' => Chat::MAX_BODY_LENGTH,
        ]);
    }
}
