<?php

namespace Tests\Feature;

use App\Events\Chat\MessageSent;
use App\Filament\Resources\ChatConversations\ChatConversationResource;
use App\Livewire\ChatThread;
use App\Livewire\ChatWidget;
use App\Models\ChatConversation;
use App\Models\ChatMessage;
use App\Models\Language;
use App\Models\Page;
use App\Models\Setting;
use App\Models\User;
use App\Support\Chat;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Broadcast;
use Illuminate\Support\Facades\Route;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * The live chat, end to end.
 *
 * A chat has three fragile parts, and each one is tested here: the token that
 * stands in for a login a guest does not have, the counters that decide what
 * still needs reading, and the socket payload, which travels in the clear and
 * must therefore carry as little as possible.
 *
 * No WebSocket runs in the test suite — `BROADCAST_CONNECTION=null` and an
 * empty `REVERB_APP_KEY` — so everything here goes through the database, which
 * is also exactly the path the `wire:poll` fallback uses in production.
 */
class ChatTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        Language::query()->create(['code' => 'en', 'name' => 'English', 'is_default' => true, 'sort_order' => 1]);
        Language::forgetCache();

        $this->admin = User::factory()->admin()->create();
    }

    public function test_the_bubble_opens_a_conversation_named_after_the_session_token(): void
    {
        $component = Livewire::test(ChatWidget::class)
            ->set('name', 'Marni')
            ->set('email', 'marni@example.test')
            ->set('draft', 'Is super nitro in stock?')
            ->call('start');

        $conversation = ChatConversation::query()->firstOrFail();

        $this->assertSame($component->get('token'), $conversation->token);
        $this->assertSame('Marni', $conversation->name);
        $this->assertSame('marni@example.test', $conversation->email);
        $this->assertSame('en', $conversation->locale);
        $this->assertTrue($conversation->isOpen());
        $this->assertSame(1, $conversation->agent_unread);
        $this->assertSame(0, $conversation->visitor_unread);

        $component->assertSet('conversationId', $conversation->getKey())->assertSet('draft', '');

        $this->assertSame([ChatMessage::SENDER_VISITOR], $conversation->messages()->orderBy('id')->pluck('sender')->all());
        $this->assertSame('Is super nitro in stock?', $conversation->messages()->orderBy('id')->value('body'));
    }

    public function test_a_written_greeting_is_the_first_line_of_a_new_conversation(): void
    {
        Setting::put('chat.greeting', 'Welcome — tell us what you need.');

        Livewire::test(ChatWidget::class)
            ->set('name', 'Zaw')
            ->set('draft', 'Hello')
            ->call('start');

        $conversation = ChatConversation::query()->firstOrFail();

        $lines = $conversation->messages()->orderBy('id')->get();

        $this->assertSame(
            [ChatMessage::SENDER_SYSTEM, ChatMessage::SENDER_VISITOR],
            $lines->pluck('sender')->all()
        );

        // Regression: writing the greeting used to clear the draft on the way,
        // so the visitor's own question was stored as an empty line.
        $this->assertSame('Welcome — tell us what you need.', $lines[0]->body);
        $this->assertSame('Hello', $lines[1]->body);

        // The greeting is a line the team has already answered, so only the
        // question makes work for the inbox.
        $this->assertSame(1, $conversation->fresh()->agent_unread);
    }

    public function test_a_name_is_required_before_a_chat_can_start(): void
    {
        Livewire::test(ChatWidget::class)
            ->set('draft', 'Just a question')
            ->call('start')
            ->assertHasErrors(['name']);

        $this->assertSame(0, ChatConversation::query()->count());
    }

    public function test_the_same_token_never_opens_a_second_conversation(): void
    {
        $first = Livewire::test(ChatWidget::class)
            ->set('name', 'Marni')
            ->set('draft', 'First')
            ->call('start')
            ->get('token');

        Livewire::test(ChatWidget::class)
            ->assertSet('token', $first)
            ->set('draft', 'Second')
            ->call('submit');

        $this->assertSame(1, ChatConversation::query()->count());
        $this->assertSame(
            ['First', 'Second'],
            ChatMessage::query()->orderBy('id')->pluck('body')->all()
        );
    }

    public function test_a_visitor_typing_faster_than_the_limiter_allows_is_ignored(): void
    {
        $component = Livewire::test(ChatWidget::class)
            ->set('name', 'Marni')
            ->set('draft', 'One')
            ->call('start');

        // Twelve messages a minute, and the first one is already spent.
        for ($i = 2; $i <= 12; $i++) {
            $component->set('draft', 'Message '.$i)->call('send');
        }

        $this->assertSame(12, ChatMessage::query()->count());

        $component->set('draft', 'Too far')->call('send')
            ->assertSet('rateLimited', true);

        $this->assertSame(12, ChatMessage::query()->count());
    }

    public function test_an_agent_reply_reaches_the_visitor_as_unread(): void
    {
        $conversation = $this->conversationWithVisitorMessage();

        $this->actingAs($this->admin);

        Livewire::test(ChatThread::class, ['conversationId' => $conversation->getKey()])
            ->set('reply', 'Yes, in stock now.')
            ->call('send');

        $conversation->refresh();

        $reply = $conversation->messages()->where('sender', ChatMessage::SENDER_AGENT)->firstOrFail();

        $this->assertSame('Yes, in stock now.', $reply->body);
        $this->assertSame($this->admin->getKey(), $reply->user_id);
        $this->assertSame(1, $conversation->visitor_unread);
        $this->assertNotNull($conversation->last_message_at);
    }

    public function test_ending_a_chat_writes_a_system_line_the_visitor_can_read(): void
    {
        $conversation = $this->conversationWithVisitorMessage();

        $this->actingAs($this->admin);

        Livewire::test(ChatThread::class, ['conversationId' => $conversation->getKey()])
            ->call('close');

        $conversation->refresh();

        $this->assertSame(ChatConversation::STATUS_CLOSED, $conversation->status);

        $last = $conversation->messages()->latest('id')->firstOrFail();

        $this->assertSame(ChatMessage::SENDER_SYSTEM, $last->sender);
        $this->assertStringContainsString((string) $conversation->email, $last->body);
    }

    public function test_a_closed_chat_reopens_when_the_visitor_writes_again(): void
    {
        $conversation = $this->conversationWithVisitorMessage();
        $conversation->close();

        Livewire::test(ChatWidget::class)
            ->set('draft', 'One more thing')
            ->call('send');

        $this->assertSame(ChatConversation::STATUS_OPEN, $conversation->fresh()->status);

        // The close notice was written between the visitor's two lines, so the
        // transcript still reads in the order it happened.
        $this->assertSame(
            [ChatMessage::SENDER_VISITOR, ChatMessage::SENDER_SYSTEM, ChatMessage::SENDER_VISITOR],
            $conversation->fresh()->messages()->orderBy('id')->pluck('sender')->all()
        );
    }

    public function test_the_widget_only_redraws_for_its_own_token(): void
    {
        $conversation = $this->conversationWithVisitorMessage();

        // The team has answered; the visitor has the panel open, so a refresh
        // that belongs to it may clear the counter.
        $this->actingAs($this->admin);

        Livewire::test(ChatThread::class, ['conversationId' => $conversation->getKey()])
            ->set('reply', 'Answered.')
            ->call('send');

        // A message for somebody else's conversation must not redraw this one.
        Livewire::test(ChatWidget::class)
            ->set('open', true)
            ->call('onMessage', ['token' => 'not-my-token']);

        $this->assertSame(1, $conversation->fresh()->visitor_unread);

        Livewire::test(ChatWidget::class)
            ->set('open', true)
            ->call('onMessage', ['token' => $conversation->token]);

        $this->assertSame(0, $conversation->fresh()->visitor_unread);
    }

    public function test_a_closed_bubble_keeps_its_dot_until_the_visitor_opens_it(): void
    {
        $conversation = $this->conversationWithVisitorMessage();

        $this->actingAs($this->admin);

        Livewire::test(ChatThread::class, ['conversationId' => $conversation->getKey()])
            ->set('reply', 'Hello there')
            ->call('send');

        // Reading the page is not reading the message.
        Livewire::test(ChatWidget::class)->call('refresh');

        $this->assertSame(1, $conversation->fresh()->visitor_unread);

        Livewire::test(ChatWidget::class)->call('toggle');

        $this->assertSame(0, $conversation->fresh()->visitor_unread);
    }

    public function test_the_broadcast_payload_names_the_conversation_but_never_reveals_the_email(): void
    {
        $conversation = $this->conversationWithVisitorMessage();

        $message = $conversation->messages()->orderBy('id')->firstOrFail();
        $event = new MessageSent($message);

        $this->assertSame('MessageSent', $event->broadcastAs());

        $names = array_map(
            fn (Channel|PrivateChannel $channel): string => $channel->name,
            $event->broadcastOn()
        );

        // Private channels reach the socket with their `private-` prefix
        // already on them, which is what Echo subscribes to.
        $this->assertContains('private-'.Chat::AGENTS_CHANNEL, $names);
        $this->assertContains(Chat::channelFor($conversation->token), $names);

        $payload = $event->broadcastWith();

        $this->assertSame($conversation->token, $payload['token']);
        $this->assertArrayNotHasKey('email', $payload);
        $this->assertStringNotContainsString('marni@example.test', json_encode($payload));
    }

    public function test_an_editor_can_see_the_inbox_and_answer_a_conversation(): void
    {
        $conversation = $this->conversationWithVisitorMessage();

        $this->actingAs($this->admin)
            ->get('/admin/chat-conversations')
            ->assertOk()
            ->assertSee('Marni');

        $this->assertSame(1, $conversation->fresh()->agent_unread);

        $this->actingAs($this->admin)
            ->get('/admin/chat-conversations/'.$conversation->getKey())
            ->assertOk()
            ->assertSee('Is super nitro in stock?');

        // Opening the conversation is reading it.
        $this->assertSame(0, $conversation->fresh()->agent_unread);
    }

    public function test_the_inbox_can_be_narrowed_to_what_is_waiting(): void
    {
        $waiting = $this->conversationWithVisitorMessage();
        $quiet = ChatConversation::query()->create([
            'token' => 'quiet-'.str_repeat('a', 34),
            'name' => 'Thiri',
            'email' => null,
            'locale' => 'en',
        ]);

        $this->assertSame(1, ChatConversation::query()->awaitingAgent()->count());
        $this->assertSame('1', ChatConversationResource::getNavigationBadge());

        $waiting->markAgentsRead();

        $this->assertSame(0, ChatConversation::query()->awaitingAgent()->count());
        $this->assertNull(ChatConversationResource::getNavigationBadge());

        $this->actingAs($this->admin)
            ->get('/admin/chat-conversations')
            ->assertOk()
            ->assertSee($quiet->name);
    }

    public function test_only_an_admin_may_join_the_agents_channel(): void
    {
        $channels = Broadcast::getChannels();

        $this->assertTrue(
            $channels->has(Chat::AGENTS_CHANNEL),
            'routes/channels.php must register the agents channel or every admin subscription is refused'
        );

        // This is the callback `/broadcasting/auth` runs, asked directly so the
        // test does not depend on which broadcaster driver happens to be
        // configured — the null one would answer anybody.
        $callback = $channels->get(Chat::AGENTS_CHANNEL);

        $this->assertFalse($callback(null));
        $this->assertFalse($callback(User::factory()->create()));
        $this->assertTrue($callback($this->admin));
    }

    public function test_echo_can_reach_the_default_authorisation_endpoint(): void
    {
        // Echo posts to `/broadcasting/auth` without being told to; if that
        // route ever stops being registered, no admin can join the agents
        // channel and the inbox quietly loses its live updates.
        $route = collect(Route::getRoutes())->first(
            fn ($candidate) => $candidate->uri() === 'broadcasting/auth' && in_array('POST', $candidate->methods(), true)
        );

        $this->assertNotNull($route);
    }

    public function test_no_echo_client_is_built_without_a_reverb_key(): void
    {
        $this->assertNull(config('filament.broadcasting.echo'));

        config()->set('filament.broadcasting.echo', ['broadcaster' => 'reverb']);

        $this->actingAs($this->admin)
            ->get('/admin/chat-conversations')
            ->assertOk()
            ->assertSee('window.Echo = new window.EchoFactory', false);
    }

    public function test_the_bubble_disappears_with_the_setting(): void
    {
        Page::query()->create([
            'kind' => Page::KIND_PAGE,
            'slug' => 'home',
            'status' => Page::STATUS_PUBLISHED,
            'published_at' => now(),
        ])->translations()->create([
            'locale' => 'en',
            'title' => 'Home',
            'content_blocks' => [],
        ]);

        Setting::put('chat.enabled', false);

        $this->get('/')->assertOk()->assertDontSee('data-chat');

        Setting::put('chat.enabled', true);

        $this->get('/')->assertOk()->assertSee('data-chat');
    }

    private function conversationWithVisitorMessage(): ChatConversation
    {
        $conversation = ChatConversation::query()->create([
            'token' => Chat::token(),
            'name' => 'Marni',
            'email' => 'marni@example.test',
            'locale' => 'en',
        ]);

        $conversation->record(ChatMessage::fromVisitor('Is super nitro in stock?'));

        return $conversation->fresh();
    }
}
