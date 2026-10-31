<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** One line of a live chat transcript. */
class ChatMessage extends Model
{
    public const SENDER_VISITOR = 'visitor';

    public const SENDER_AGENT = 'agent';

    public const SENDER_SYSTEM = 'system';

    /** @var list<string> */
    protected $fillable = [
        'chat_conversation_id',
        'sender',
        'user_id',
        'body',
    ];

    /** @var array<string, mixed> */
    protected $attributes = ['sender' => self::SENDER_VISITOR];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [];
    }

    /** @return BelongsTo<ChatConversation, ChatMessage> */
    public function conversation(): BelongsTo
    {
        return $this->belongsTo(ChatConversation::class, 'chat_conversation_id');
    }

    /** @return BelongsTo<User, ChatMessage> */
    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public static function fromVisitor(string $body): self
    {
        return new self(['sender' => self::SENDER_VISITOR, 'body' => $body]);
    }

    public static function fromAgent(string $body, ?User $author = null): self
    {
        return new self([
            'sender' => self::SENDER_AGENT,
            'body' => $body,
            'user_id' => $author?->getKey(),
        ]);
    }

    /** A line nobody wrote, such as the closing note. */
    public static function system(string $body): self
    {
        return new self(['sender' => self::SENDER_SYSTEM, 'body' => $body]);
    }

    public function isFromVisitor(): bool
    {
        return $this->sender === self::SENDER_VISITOR;
    }

    public function isFromAgent(): bool
    {
        return $this->sender === self::SENDER_AGENT;
    }

    /**
     * The name shown above the bubble. An agent's own name is used rather than
     * a generic label, because the visitor cannot tell two replies apart.
     */
    public function authorName(): string
    {
        return match ($this->sender) {
            self::SENDER_AGENT => $this->author?->name ?? __('ui.chat.team'),
            self::SENDER_VISITOR => $this->conversation?->displayName() ?? __('ui.chat.you'),
            default => __('ui.chat.system'),
        };
    }

    /**
     * What crosses the wire. Kept to the fields both panels draw so a client
     * never receives an email address it has no business seeing.
     *
     * @return array<string, mixed>
     */
    public function toBroadcast(): array
    {
        return [
            'id' => $this->getKey(),
            'conversation' => $this->chat_conversation_id,
            'token' => $this->conversation?->token,
            'sender' => $this->sender,
            'body' => $this->body,
            'author' => $this->sender === self::SENDER_SYSTEM ? null : $this->authorName(),
            'at' => $this->created_at?->toIso8601String(),
        ];
    }
}
