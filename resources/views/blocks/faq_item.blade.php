@php
    $question = trim((string) ($data['question'] ?? ''));
    $answer = (string) ($data['answer'] ?? '');
    $context = trim((string) ($data['context'] ?? ''));
@endphp

@if ($question !== '')
    <section class="scroll-mt-24 py-8" id="{{ \Illuminate\Support\Str::slug($question) }}">
        <div class="mx-auto max-w-3xl px-4 sm:px-6 lg:px-8">
            <div class="faq-item" data-faq data-reveal>
                <button type="button" class="faq-trigger" data-faq-toggle aria-expanded="true">
                    <span>{{ $question }}</span>
                    <span class="faq-item__icon" aria-hidden="true">
                        <x-site.icon name="plus" class="h-4 w-4" />
                    </span>
                </button>

                <div class="faq-panel is-open" aria-hidden="false">
                    <div>
                        <div class="faq-body rich-text">
                            {!! rich_text($answer) !!}
                        </div>
                    </div>
                </div>
            </div>

            @if ($context !== '')
                <p class="mt-3 px-1 text-xs font-semibold tracking-[0.18em] text-stone-400 uppercase">{{ $context }}</p>
            @endif
        </div>
    </section>
@endif
