{{--
    The conversation, from the team's side.

    Layout lives in `public/css/app/chat-thread.css` (registered with the panel)
    rather than in Tailwind classes, because Filament's stylesheet only contains
    the utilities Filament itself uses. Buttons, badges and the input wrapper are
    Filament's own components, so the thread keeps the panel's colours, dark mode
    and focus rings.
--}}
<div class="chat-thread" wire:key="chat-thread-{{ $conversationId }}">
    <header class="chat-thread__head">
        <div>
            <p class="chat-thread__who">{{ $conversation?->displayName() ?? __('ui.chat.guest') }}</p>
            <p class="chat-thread__meta">
                @if (filled($conversation?->email))
                    {{ $conversation->email }}
                    <span aria-hidden="true">·</span>
                @endif

                {{ Str::upper((string) $conversation?->locale) }}
                <span aria-hidden="true">·</span>
                {{ $conversation?->created_at?->diffForHumans() }}
            </p>
        </div>

        @if ($conversation)
            <x-filament::badge :color="$conversation->isOpen() ? 'success' : 'gray'">
                {{ \App\Models\ChatConversation::statusOptions()[$conversation->status] ?? $conversation->status }}
            </x-filament::badge>
        @endif
    </header>

    <div class="chat-thread__log" wire:poll.15s="refresh">
        @if ($messages->isEmpty())
            <p class="chat-thread__empty">{{ __('ui.chat.no_messages') }}</p>
        @else
            @foreach ($messages->reverse() as $message)
                <div
                    class="chat-thread__row chat-thread__row--{{ $message->sender }}"
                    wire:key="chat-thread-message-{{ $message->getKey() }}"
                >
                    <p class="chat-thread__bubble">{{ $message->body }}</p>
                    <p class="chat-thread__author">
                        {{ $message->authorName() }}
                        <span aria-hidden="true">·</span>
                        <time datetime="{{ $message->created_at?->toIso8601String() }}">
                            {{ $message->created_at?->diffForHumans() }}
                        </time>
                    </p>
                </div>
            @endforeach
        @endif
    </div>

    <form class="chat-thread__composer" wire:submit="send">
        <label class="fi-sr-only" for="chat-thread-reply">{{ __('ui.chat.reply') }}</label>

        <x-filament::input.wrapper>
            <textarea
                id="chat-thread-reply"
                class="fi-input chat-thread__textarea"
                rows="3"
                maxlength="{{ $maxLength }}"
                wire:model="reply"
                wire:keydown.enter.exact.prevent="send"
                placeholder="{{ __('ui.chat.reply_placeholder') }}"
            ></textarea>
        </x-filament::input.wrapper>

        @error('reply')
            <p class="chat-thread__error">{{ $message }}</p>
        @enderror

        <div class="chat-thread__actions">
            <x-filament::button type="submit" icon="heroicon-o-paper-airplane">
                {{ __('ui.chat.send_reply') }}
            </x-filament::button>

            @if ($conversation?->isOpen())
                <x-filament::button
                    type="button"
                    color="gray"
                    icon="heroicon-o-x-circle"
                    outlined
                    wire:click="close"
                    wire:confirm="{{ __('ui.chat.end_confirm') }}"
                >
                    {{ __('ui.chat.end_chat') }}
                </x-filament::button>
            @endif
        </div>
    </form>
</div>
