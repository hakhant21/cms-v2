<?php

namespace App\Support;

use App\Events\Chat\MessageSent;
use App\Models\ChatConversation;
use App\Models\ChatMessage;
use App\Models\Setting;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Throwable;

/**
 * The rules the live chat plays by, in one place: which channel a conversation
 * broadcasts on, how a visitor proves ownership of it, and how hard a single
 * visitor may type.
 *
 * A visitor is not logged in, so the conversation's token is the credential.
 * It is 40 characters of randomness kept in the session, and it names the
 * public broadcast channel — the server never authorises it, because knowing
 * the token *is* the authorisation. Guessing someone else's channel would mean
 * guessing 240 bits, which is why no signed auth request is needed for guests.
 */
final class Chat
{
    /** Public channels are named after the conversation token. */
    public const CHANNEL_PREFIX = 'chat.conversation.';

    /** The one private channel every admin joins; `/broadcasting/auth` guards it. */
    public const AGENTS_CHANNEL = 'chat.agents';

    public const MAX_BODY_LENGTH = 1000;

    /** Session keys, kept short and namespaced to avoid collisions. */
    public const SESSION_TOKEN = 'chat.token';

    public const SESSION_CONVERSATION = 'chat.conversation_id';

    /** Messages per minute a single visitor may push, before a silent refusal. */
    private const MAX_MESSAGES_PER_MINUTE = 12;

    public static function enabled(): bool
    {
        return Setting::bool('chat.enabled', true);
    }

    public static function greeting(): ?string
    {
        return Setting::get('chat.greeting');
    }

    public static function channelFor(string $token): string
    {
        return self::CHANNEL_PREFIX.$token;
    }

    /**
     * Does an incoming broadcast payload belong to this visitor's conversation?
     *
     * The payload arrives on a public channel, so the token inside it is
     * compared rather than trusted — and compared in constant time, because it
     * is the only credential a guest ever has.
     */
    public static function ownsToken(string $token, mixed $candidate): bool
    {
        return is_string($candidate) && hash_equals($token, $candidate);
    }

    /**
     * The conversation token for this browser, created once and then reused by
     * every page view — the channel a visitor listens on is decided at the
     * first render and must not change underneath the subscription.
     */
    public static function token(): string
    {
        $token = session(self::SESSION_TOKEN);

        if (! is_string($token) || strlen($token) !== 40) {
            $token = Str::random(40);
            session([self::SESSION_TOKEN => $token]);
        }

        return $token;
    }

    /**
     * Has this visitor sent too many messages recently? Used instead of a
     * route middleware because a chat bubble has no URL to throttle.
     */
    public static function throttled(string $token): bool
    {
        if (RateLimiter::tooManyAttempts('chat:'.$token, self::MAX_MESSAGES_PER_MINUTE)) {
            return true;
        }

        RateLimiter::hit('chat:'.$token, 60);

        return false;
    }

    /** The conversation this session owns, if it has really started one. */
    public static function currentConversation(): ?ChatConversation
    {
        $token = session(self::SESSION_TOKEN);

        return is_string($token) && $token !== ''
            ? ChatConversation::query()->where('token', $token)->first()
            : null;
    }

    /**
     * Tell both sides about a stored message.
     *
     * The broadcast is deliberately not allowed to fail the request: the row is
     * already written, so a stopped Reverb process costs the instant delivery
     * and nothing else — the other half still reads the message on its next
     * poll, and the visitor who just typed does not get an error page.
     */
    public static function announce(ChatMessage $message): void
    {
        try {
            event(new MessageSent($message));
        } catch (Throwable $e) {
            report($e);
        }
    }
}
