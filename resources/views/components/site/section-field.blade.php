@props(['field', 'tone' => 'light'])

@php
    $type = (string) ($field['type'] ?? '');
    $value = $field['value'] ?? null;
    $alt = (string) ($field['alt'] ?? '');
    $url = (string) ($field['url'] ?? '');
    $items = is_array($field['items'] ?? null) ? $field['items'] : [];

    $dark = $tone === 'dark' || $tone === 'hero';

    // A section's video field stores whatever link was pasted, so the player
    // address is resolved here rather than at save time.
    $embedSrc = $type === 'video' ? \App\Support\VideoEmbed::url(is_string($value) ? $value : null) : null;
    $eyebrowClass = $dark
        ? 'text-xs font-semibold tracking-[0.25em] text-lime-300 uppercase'
        : 'text-xs font-semibold tracking-[0.25em] text-green-600 uppercase';
    $titleClass = $dark
        ? 'text-3xl font-bold text-white sm:text-4xl'
        : 'text-3xl font-bold text-stone-900 sm:text-4xl';
    $bodyClass = $dark ? 'text-lg leading-8 text-stone-100/85' : 'text-base leading-8 text-stone-700';
@endphp

@switch($type)
    @case('eyebrow')
        <p class="{{ $eyebrowClass }}">{{ $value }}</p>
        @break

    @case('title')
        <h2 class="{{ $titleClass }}">{{ $value }}</h2>
        @break

    @case('subtitle')
        <p class="{{ $dark ? 'text-lg font-medium text-lime-100/90' : 'text-lg font-medium text-stone-600' }}">{{ $value }}</p>
        @break

    @case('paragraph')
    @case('profile')
    @case('mission')
    @case('vision')
        @if (in_array($type, ['profile', 'mission', 'vision'], true))
            <p class="{{ $eyebrowClass }}">{{ ucfirst($type) }}</p>
        @endif
        <p class="{{ $bodyClass }}">{{ $value }}</p>
        @break

    @case('rich_text')
        <div class="prose mt-4 max-w-none {{ $dark ? 'prose-invert' : 'prose-stone' }}">
            {!! rich_text(is_string($value) ? $value : null) !!}
        </div>
        @break

    @case('image')
        @if ($src = media_url(is_string($value) ? $value : null))
            {{-- A single picture is clickable too: a lab report is read best at
                 full width, and the lightbox keeps the page underneath. --}}
            <figure class="mt-6">
                <a
                    href="{{ $src }}"
                    data-lightbox
                    data-lightbox-caption="{{ $alt }}"
                    class="group block"
                >
                    <img
                        src="{{ $src }}"
                        alt="{{ $alt }}"
                        class="w-full cursor-zoom-in rounded-3xl object-cover shadow-lg transition duration-700 group-hover:scale-[1.01]"
                        loading="lazy"
                        decoding="async"
                    >
                </a>
                @if ($alt !== '')
                    <figcaption class="mt-2 text-xs text-stone-500">{{ $alt }}</figcaption>
                @endif
            </figure>
        @endif
        @break

    @case('video')
        @if ($embedSrc !== null)
            <div class="mt-6 aspect-video w-full overflow-hidden rounded-3xl bg-stone-100 shadow-lg">
                <iframe
                    src="{{ $embedSrc }}"
                    title="{{ $alt ?: 'Video' }}"
                    class="h-full w-full"
                    allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture; web-share"
                    allowfullscreen
                    loading="lazy"
                ></iframe>
            </div>
        @endif
        @break

    @case('gallery')
        <div class="mt-6">
            <x-site.media-gallery :images="collect($items)->map(fn ($item) => ['src' => media_url((string) ($item['src'] ?? '')), 'alt' => (string) ($item['alt'] ?? '')])->filter(fn ($item) => $item['src'] !== null)->values()->all()" :alt="$alt" />
        </div>
        @break

    @case('globe')
        <div class="mt-8">
            <x-site.earth-connection :caption="is_string($value) ? $value : null" />
        </div>
        @break

    @default
        @if (in_array($type, ['list', 'milestones', 'checklist', 'stats', 'pills'], true) && $items !== [])
            <div class="{{ $type === 'checklist' ? 'mt-6' : 'mt-6 grid gap-4 sm:grid-cols-2 lg:grid-cols-3' }}">
                @if ($type === 'checklist')
                    <x-site.checklist :items="collect($items)->map(fn ($item) => (string) ($item['text'] ?? ''))->filter()->values()->all()" :columns="2" />
                @else
                    @foreach ($items as $item)
                        <div @class([
                            'rounded-2xl p-5',
                            'bg-white/10 ring-1 ring-white/15 backdrop-blur' => $dark,
                            'bg-white shadow-sm ring-1 ring-stone-100' => ! $dark,
                        ])>
                            @if (filled($item['title'] ?? $item['year'] ?? $item['value'] ?? null))
                                <p @class(['font-semibold', 'text-white' => $dark, 'text-stone-900' => ! $dark])>
                                    {{ $item['title'] ?? $item['year'] ?? $item['value'] }}
                                </p>
                            @endif
                            @if (filled($item['text'] ?? null))
                                <p @class(['mt-1 text-sm leading-6', 'text-stone-100/80' => $dark, 'text-stone-600' => ! $dark])>
                                    {{ $item['text'] }}
                                </p>
                            @endif
                        </div>
                    @endforeach
                @endif
            </div>
        @endif
        @break

    @case('button')
        @if ($value)
            <a
                href="{{ $url !== '' ? locale_path($url) : '#' }}"
                @class([
                    'btn-glow mt-6 inline-flex items-center gap-2 rounded-full px-8 py-3.5 text-sm font-semibold',
                    'bg-lime-300 text-green-950' => $dark,
                    'bg-green-700 text-white' => ! $dark,
                ])
            >
                {{ $value }}
                <x-site.icon name="arrow-right" class="h-4 w-4" />
            </a>
        @endif
        @break
@endswitch
