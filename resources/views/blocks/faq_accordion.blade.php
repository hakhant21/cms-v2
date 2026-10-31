@php
    $items = is_array($data['items'] ?? null) ? $data['items'] : [];
    $title = filled($data['title'] ?? null) ? (string) $data['title'] : null;
@endphp

@if ($items !== [])
    <section class="scroll-mt-24 py-14 sm:py-20">
        <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
            <div class="grid gap-10 lg:grid-cols-[minmax(0,0.9fr)_minmax(0,1.6fr)] lg:gap-16">
                <div data-reveal>
                    @if ($title)
                        <p class="content-eyebrow">{{ __('ui.nav.resources') }}</p>
                        <h2 class="mt-4 text-3xl font-black tracking-tight text-stone-900 sm:text-4xl">{{ $title }}</h2>
                    @endif

                    @if (filled($data['context'] ?? null))
                        <p class="mt-3 text-sm text-stone-500">
                            {{ __('ui.common.more') }}: <span class="font-semibold text-green-700">{{ $data['context'] }}</span>
                        </p>
                    @endif
                </div>

                <div class="space-y-3" data-reveal="right">
                    @foreach ($items as $index => $item)
                        @php
                            $question = trim((string) ($item['data']['question'] ?? $item['title']));
                            $answer = (string) ($item['data']['answer'] ?? '');
                            $open = $index === 0;
                        @endphp

                        <div @class(['faq-item', 'is-open' => $open]) data-faq>
                            <button
                                type="button"
                                class="faq-trigger"
                                data-faq-toggle
                                aria-expanded="{{ $open ? 'true' : 'false' }}"
                            >
                                <span>{{ $question }}</span>
                                <span class="faq-item__icon" aria-hidden="true">
                                    <x-site.icon name="plus" class="h-4 w-4" />
                                </span>
                            </button>

                            <div class="faq-panel" aria-hidden="{{ $open ? 'false' : 'true' }}">
                                <div>
                                    <div class="faq-body rich-text">
                                        {!! rich_text($answer) !!}
                                    </div>
                                </div>
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
        </div>
    </section>
@endif
