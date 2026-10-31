{{--
    The live chat bubble.

    The log is rendered newest-first inside a column-reverse box, which keeps it
    pinned to the latest line without a single line of JavaScript: the browser's
    own scroll anchoring does it, and a visitor reading older lines is not
    dragged to the bottom when a reply lands.
--}}
<div class="chat" wire:key="chat-{{ $token }}" data-chat>
    @if ($open)
        <section
            id="chat-panel"
            class="chat__panel"
            role="dialog"
            aria-labelledby="chat-panel-title"
            @if ($conversation) wire:poll.6s="refresh" @endif
        >
            <header class="chat__head">
                <div>
                    <h2 class="chat__title" id="chat-panel-title">{{ __('ui.chat.title') }}</h2>
                    <p class="chat__state" data-chat-state>
                        @if (! $conversation)
                            {{ __('ui.chat.intro') }}
                        @elseif (! $conversation->isOpen())
                            {{ __('ui.chat.closed_banner') }}
                        @else
                            {{ __('ui.chat.online') }}
                        @endif
                    </p>
                </div>

                <button type="button" class="chat__dismiss" wire:click="close" aria-label="{{ __('ui.chat.minimize') }}">
                    <x-site.icon name="close" class="h-5 w-5" />
                </button>
            </header>

            <div class="chat__log" data-chat-log>
                @if ($messages->isEmpty())
                    <p class="chat__empty">{{ __('ui.chat.empty') }}</p>
                @else
                    @foreach ($messages->reverse() as $message)
                        <div
                            class="chat__row chat__row--{{ $message->sender }}"
                            wire:key="chat-message-{{ $message->getKey() }}"
                        >
                            <p class="chat__bubble">{{ $message->body }}</p>
                            <p class="chat__meta">
                                {{ $message->isFromVisitor() ? __('ui.chat.you') : $message->authorName() }}
                                <span aria-hidden="true">·</span>
                                <time datetime="{{ $message->created_at?->toIso8601String() }}">
                                    {{ $message->created_at?->diffForHumans() }}
                                </time>
                            </p>
                        </div>
                    @endforeach
                @endif
            </div>

            <form class="chat__composer" wire:submit="submit">
                @if ($conversation === null)
                    <div class="chat__identity">
                        <div class="chat__field">
                            <label class="chat__label" for="chat-name">{{ __('ui.chat.name') }}</label>
                            <input
                                id="chat-name"
                                type="text"
                                class="chat__input"
                                wire:model="name"
                                autocomplete="name"
                                maxlength="120"
                                required
                            >
                            @error('name')
                                <p class="chat__error">{{ $message }}</p>
                            @enderror
                        </div>

                        <div class="chat__field">
                            <label class="chat__label" for="chat-email">{{ __('ui.chat.email') }}</label>
                            <input
                                id="chat-email"
                                type="email"
                                class="chat__input"
                                wire:model="email"
                                autocomplete="email"
                                maxlength="180"
                                placeholder="{{ __('ui.chat.email_optional') }}"
                            >
                            @error('email')
                                <p class="chat__error">{{ $message }}</p>
                            @enderror
                        </div>
                    </div>
                @endif

                <div class="chat__write">
                    <label class="sr-only" for="chat-draft">{{ __('ui.chat.placeholder') }}</label>
                    <textarea
                        id="chat-draft"
                        class="chat__textarea"
                        rows="2"
                        maxlength="{{ $maxLength }}"
                        wire:model="draft"
                        wire:keydown.enter.exact.prevent="submit"
                        data-chat-draft
                        placeholder="{{ __('ui.chat.placeholder') }}"
                        aria-describedby="chat-hint"
                        required
                    ></textarea>

                    <button type="submit" class="chat__send" aria-label="{{ __('ui.chat.send') }}">
                        <x-site.icon name="send" class="h-5 w-5" />
                    </button>
                </div>

                @error('draft')
                    <p class="chat__error">{{ $message }}</p>
                @enderror

                @if ($rateLimited)
                    <p class="chat__error">{{ __('ui.chat.too_fast') }}</p>
                @endif

                <p class="chat__hint" id="chat-hint">{{ __('ui.chat.hint') }}</p>
            </form>
        </section>
    @endif

    <button
        type="button"
        class="chat__bubble-button"
        wire:click="toggle"
        aria-expanded="{{ $open ? 'true' : 'false' }}"
        aria-controls="chat-panel"
    >
        <x-site.icon name="{{ $open ? 'close' : 'chat' }}" class="h-6 w-6" aria-hidden="true" />

        @if ($unread > 0 && ! $open)
            <span class="chat__dot" aria-hidden="true"></span>
            <span class="sr-only">{{ trans_choice('ui.chat.unread', $unread, ['count' => $unread]) }}</span>
        @endif

        <span class="chat__bubble-label">{{ $open ? __('ui.chat.minimize') : __('ui.chat.open') }}</span>
    </button>
</div>
