@php
    $anchor = $section->anchor();
    $list = $section->field('list');
    $items = $section->rows($list);
    // A list whose rows carry "— Heading" markers is a grouped feature list, not
    // a card grid; rendering it as numbered cards would turn every heading into
    // one more card. `groups()` returns null for an ordinary list, so home and
    // about keep their numbered cards.
    $groups = $section->groups($list);
    $gallery = $section->gallery();
    $button = $section->button();

    /* Anything that is not the heading copy or the card list still has to
       render, so rich text and media are folded in below the cards. */
    $extras = array_values(array_filter(
        $section->fields(),
        fn (array $field): bool => ! in_array($field['type'], ['eyebrow', 'title', 'subtitle', 'paragraph', 'list'], true),
    ));
@endphp

<section
    @if ($anchor !== '') id="{{ $anchor }}" @endif
    class="scroll-mt-24 py-14 sm:py-20"
>
    <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
        <x-site.section-heading :section="$section" align="center" />

        @if ($groups !== null)
            <div class="mt-12 grid gap-6 md:grid-cols-2" data-reveal-group>
                @foreach ($groups as $group)
                    <article class="content-card p-6 sm:p-8" data-reveal>
                        <h3 class="text-lg font-bold text-stone-900">{{ $group['label'] }}</h3>

                        @if ($group['items'] !== [])
                            <ul class="mt-4 space-y-3">
                                @foreach ($group['items'] as $item)
                                    <li class="benefits-checklist__item">
                                        <span class="benefits-checklist__tick" aria-hidden="true">
                                            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><path d="M20 6 9 17l-5-5" /></svg>
                                        </span>
                                        <span>{{ $item }}</span>
                                    </li>
                                @endforeach
                            </ul>
                        @endif
                    </article>
                @endforeach
            </div>
        @elseif ($items !== [])
            <div class="mt-12 grid gap-5 sm:grid-cols-2 lg:grid-cols-3" data-reveal-group>
                @foreach ($items as $index => $item)
                    <article class="content-card p-6 sm:p-7" data-reveal>
                        <span class="content-card__index">{{ str_pad((string) ($index + 1), 2, '0', STR_PAD_LEFT) }}</span>

                        @if (filled($item['title'] ?? null))
                            <h3 class="mt-3 text-lg font-bold text-stone-900">{{ $item['title'] }}</h3>
                        @endif

                        @if (filled($item['text'] ?? null))
                            <p class="mt-2 text-sm leading-7 text-stone-600">{{ $item['text'] }}</p>
                        @endif
                    </article>
                @endforeach
            </div>
        @endif

        @if ($gallery !== [])
            <div class="mt-12">
                <x-site.media-gallery :images="$gallery" :alt="$section->value('title') ?? ''" />
            </div>
        @endif

        @foreach ($extras as $field)
            @if ($field['type'] === 'button' || $field['type'] === 'gallery')
                @continue
            @endif

            <div class="mx-auto mt-10 max-w-3xl text-center" data-reveal>
                <x-site.section-field :field="$field" />
            </div>
        @endforeach

        @if ($button !== null)
            <div class="mt-12 text-center" data-reveal>
                <a
                    href="{{ $button['url'] !== '' ? locale_path($button['url']) : '#' }}"
                    @if (! \Illuminate\Support\Str::startsWith($button['url'], ['http://', 'https://'])) wire:navigate @endif
                    class="btn-glow inline-flex items-center gap-2 rounded-full bg-green-700 px-8 py-3.5 text-sm font-semibold text-white"
                >
                    {{ $button['label'] }}
                    <x-site.icon name="arrow-right" class="h-4 w-4" />
                </a>
            </div>
        @endif
    </div>
</section>
