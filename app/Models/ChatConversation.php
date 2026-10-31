<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Collection;

/**
 * A live chat between one visitor and the team.
 *
 * The token is the only thing that ties a browser to its conversation, so it
 * is generated before any row exists (the visitor's session holds it from the
 * first page view) and stored here when the chat really starts.
 */
class ChatConversation extends Model
{
    public const STATUS_OPEN = 'open';

    public const STATUS_CLOSED = 'closed';

    /** @var list<string> */
    protected $fillable = [
        'token',
        'name',
        'email',
        'locale',
        'status',
        'last_message_at',
        'agent_unread',
        'visitor_unread',
        'assigned_user_id',
    ];

    /**
     * Mirrors the column default so a model that has not been saved yet already
     * behaves like a live conversation.
     *
     * @var array<string, mixed>
     */
    protected $attributes = ['status' => self::STATUS_OPEN];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'last_message_at' => 'datetime',
            'agent_unread' => 'integer',
            'visitor_unread' => 'integer',
        ];
    }

    /** @return array<string, string> */
    public static function statusOptions(): array
    {
        return [
            self::STATUS_OPEN => 'Open',
            self::STATUS_CLOSED => 'Closed',
        ];
    }

    /**
     * The transcript. No default order: a relation that orders itself quietly
     * turns every later `latest()` into a second ORDER BY that loses, which is
     * how a `limit()` would start returning the oldest lines instead of the
     * newest. Callers say which way they want to read it.
     *
     * @return HasMany<ChatMessage>
     */
    public function messages(): HasMany
    {
        return $this->hasMany(ChatMessage::class);
    }

    /**
     * The line the inbox list shows, loaded in one query for the whole page.
     *
     * @return HasOne<ChatMessage>
     */
    public function lastMessage(): HasOne
    {
        return $this->hasOne(ChatMessage::class)->latestOfMany('id');
    }

    /** @return BelongsTo<User, ChatConversation> */
    public function assignedTo(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_user_id');
    }

    /** @return Builder<static> */
    public function scopeOpen(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_OPEN);
    }

    /** Conversations with something waiting for a human to read. */
    public function scopeAwaitingAgent(Builder $query): Builder
    {
        return $query->open()->where('agent_unread', '>', 0);
    }

    public function isOpen(): bool
    {
        return $this->status === self::STATUS_OPEN;
    }

    /** The most recent transcript, oldest first, capped for the widget. */
    public function transcript(int $limit = 60): Collection
    {
        $messages = $this->messages()
            ->with('author')
            ->orderByDesc('id')
            ->limit($limit)
            ->get()
            ->sortBy('id')
            ->values();

        // Every line asks who wrote it, and a visitor line answers with the
        // conversation's own name. Linking the relation here keeps that cheap
        // instead of firing one query per message while the view renders.
        $messages->each(fn (ChatMessage $message): ChatMessage => $message->setRelation('conversation', $this));

        return $messages;
    }

    /**
     * Store a message and move the counters with it. The sender never counts
     * its own words as something it has not read yet.
     */
    public function record(ChatMessage $message): ChatMessage
    {
        $message->chat_conversation_id = $this->getKey();

        // The broadcast payload asks who the message belongs to; linking the
        // conversation here saves a query on the way out of `record()`.
        $message->setRelation('conversation', $this);
        $message->save();

        $incoming = $message->sender === ChatMessage::SENDER_VISITOR ? 'agent_unread' : 'visitor_unread';

        $this->forceFill([
            'last_message_at' => $message->created_at ?? now(),
            $incoming => $this->{$incoming} + 1,
        ])->save();

        return $message;
    }

    public function markAgentsRead(): void
    {
        if ($this->agent_unread > 0) {
            $this->forceFill(['agent_unread' => 0])->save();
        }
    }

    public function markVisitorRead(): void
    {
        if ($this->visitor_unread > 0) {
            $this->forceFill(['visitor_unread' => 0])->save();
        }
    }

    /**
     * End the chat. The note is written into the transcript because that is the
     * only place either side reads afterwards, and it is handed back so the
     * caller can broadcast it — a closed chat the visitor is still waiting in
     * is the worst kind of silence.
     */
    public function close(?string $note = null): ?ChatMessage
    {
        if (! $this->isOpen()) {
            return null;
        }

        $message = $this->record(ChatMessage::system($note ?? __('ui.chat.closed')));

        $this->forceFill(['status' => self::STATUS_CLOSED])->save();

        return $message;
    }

    public function reopen(): void
    {
        $this->forceFill(['status' => self::STATUS_OPEN])->save();
    }

    /** Shortest label for the inbox list: who is this, as far as we know. */
    public function displayName(): string
    {
        return trim((string) $this->name) !== '' ? $this->name : __('ui.chat.guest');
    }
}
