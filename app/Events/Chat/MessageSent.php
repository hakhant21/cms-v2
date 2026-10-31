<?php

namespace App\Events\Chat;

use App\Models\ChatMessage;
use App\Support\Chat;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * A new line in a transcript, sent to both sides at once.
 *
 * It goes out synchronously: a chat message that waits on a queue worker is a
 * chat message that arrives late on every host that does not run one, and one
 * small payload per keystroke-free send is not worth the ordering guarantees.
 *
 * Two channels carry it. The visitor's own channel is public and named after a
 * 40-character secret, so a guest receives replies without ever signing in;
 * `chat.agents` is private, because an admin list of other people's
 * conversations must not be open to whoever holds the (public) app key.
 */
class MessageSent implements ShouldBroadcastNow
{
    use Dispatchable;

    public function __construct(public ChatMessage $message) {}

    /**
     * The name the browser listens for. Set explicitly because the default
     * would be the class name, and this event crosses a namespace.
     */
    public function broadcastAs(): string
    {
        return 'MessageSent';
    }

    /** @return list<Channel|PrivateChannel> */
    public function broadcastOn(): array
    {
        $channels = [new PrivateChannel(Chat::AGENTS_CHANNEL)];

        // A message that somehow has no conversation cannot reach a visitor,
        // but the team still deserves to see it in their inbox.
        if (filled($token = $this->message->conversation?->token)) {
            $channels[] = new Channel(Chat::channelFor((string) $token));
        }

        return $channels;
    }

    /**
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        return $this->message->toBroadcast();
    }
}
