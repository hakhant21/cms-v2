@php
    $anchor = $section->anchor();
    $media = $section->mediaField();
    $button = $section->button();

    $isVideo = ($media['type'] ?? null) === 'video';
    $src = $media !== null && ! $isVideo
        ? media_url(is_string($media['value'] ?? null) ? $media['value'] : null)
        : null;

    $copy = array_values(array_filter(
        $section->fields(),
        fn (array $field): bool => ! in_array($field['type'], [
            'image', 'video', 'gallery', 'button', 'list', 'milestones', 'checklist', 'stats', 'pills',
        ], true),
    ));

    $lists = array_values(array_filter(
        $section->fields(),
        fn (array $field): bool => in_array($field['type'], ['list', 'milestones', 'checklist', 'stats', 'pills'], true),
    ));

    $gallery = $section->gallery();
@endphp

<section
    @if ($anchor !== '') id="{{ $anchor }}" @endif
    class="scroll-mt-24 py-14 sm:py-20"
>
    <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
        <div @class([
            'grid items-center gap-10 lg:gap-16',
            'lg:grid-cols-2' => $media !== null,
        ])>
            <div data-reveal="left">
                <x-site.section-heading :section="$section" width="4xl" />

                @foreach ($copy as $field)
                    @if (in_array($field['type'], ['eyebrow', 'title', 'subtitle', 'paragraph'], true))
                        @continue
                    @endif

                    <div class="mt-6">
                        <x-site.section-field :field="$field" />
                    </div>
                @endforeach

                @foreach ($lists as $field)
                    <div class="mt-8">
                        <x-site.section-field :field="$field" />
                    </div>
                @endforeach

                @if ($button !== null)
                    <a
                        href="{{ $button['url'] !== '' ? locale_path($button['url']) : '#' }}"
                        class="btn-glow mt-8 inline-flex items-center gap-2 rounded-full bg-green-700 px-8 py-3.5 text-sm font-semibold text-white"
                    >
                        {{ $button['label'] }}
                        <x-site.icon name="arrow-right" class="h-4 w-4" />
                    </a>
                @endif
            </div>

            @if ($media !== null)
                <div class="relative" data-reveal="right">
                    {{-- The glow bleeds past the edge on wide screens only; on a
                         phone that bleed was what made the page scroll sideways. --}}
                    <span class="absolute inset-0 -z-10 rounded-[2.5rem] bg-lime-300/20 blur-2xl sm:-inset-4" aria-hidden="true"></span>

                    @if ($isVideo)
                        <div class="aspect-video w-full overflow-hidden rounded-3xl bg-stone-900 shadow-2xl">
                            <iframe
                                src="{{ $media['value'] }}"
                                title="{{ $section->value('title') ?? 'Video' }}"
                                class="h-full w-full"
                                allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture"
                                allowfullscreen
                                loading="lazy"
                            ></iframe>
                        </div>
                    @elseif ($src)
                        <figure class="overflow-hidden rounded-3xl shadow-2xl ring-1 ring-stone-900/5">
                            <img
                                src="{{ $src }}"
                                alt="{{ (string) ($media['alt'] ?? '') }}"
                                class="h-full w-full object-cover"
                                loading="lazy"
                                decoding="async"
                            >
                            @if (filled($media['alt'] ?? null))
                                <figcaption class="bg-white/90 px-5 py-3 text-xs text-stone-500">
                                    {{ $media['alt'] }}
                                </figcaption>
                            @endif
                        </figure>
                    @endif
                </div>
            @endif
        </div>

        @if ($gallery !== [])
            <div class="mt-14">
                <x-site.media-gallery :images="$gallery" :alt="$section->value('title') ?? ''" />
            </div>
        @endif
    </div>
</section>
