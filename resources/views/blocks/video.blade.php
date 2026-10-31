@php
    use App\Support\VideoEmbed;

    /* The editor's link is kept as it was pasted; the player address is derived
       from it, so a watch page, a share link and a Shorts address all work. */
    $link = trim((string) ($data['url'] ?? ''));
    $src = VideoEmbed::url($link);
    $poster = VideoEmbed::thumbnail($link);
    $title = filled($data['title'] ?? null) ? (string) $data['title'] : null;
    $caption = filled($data['caption'] ?? null) ? (string) $data['caption'] : null;
    $portrait = ($data['aspect'] ?? 'landscape') === 'portrait';
@endphp

@if ($src !== null)
    <section class="scroll-mt-24 py-12 sm:py-16">
        <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
            @if ($title)
                <div class="mx-auto max-w-3xl text-center" data-reveal>
                    <h2 class="text-3xl font-black tracking-tight text-stone-900 sm:text-4xl">{{ $title }}</h2>
                </div>
            @endif

            <figure @class(['mx-auto', 'mt-8 max-w-md' => $portrait, 'mt-10' => ! $portrait]) data-reveal>
                <div @class([
                    'group relative overflow-hidden rounded-[2rem] bg-stone-900 shadow-2xl ring-1 ring-stone-900/10',
                    'aspect-9/16' => $portrait,
                    'aspect-video' => ! $portrait,
                ])>
                    @if ($poster)
                        {{-- A still shows while the player boots, so the panel is
                             never an empty black box on a slow connection. --}}
                        <img
                            src="{{ $poster }}"
                            alt=""
                            aria-hidden="true"
                            class="absolute inset-0 h-full w-full object-cover"
                            loading="lazy"
                            decoding="async"
                        >
                    @endif

                    <iframe
                        src="{{ $src }}"
                        title="{{ $title ?: ($caption ?: 'Video') }}"
                        class="absolute inset-0 h-full w-full"
                        loading="lazy"
                        allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture; web-share"
                        referrerpolicy="strict-origin-when-cross-origin"
                        allowfullscreen
                    ></iframe>
                </div>

                @if ($caption)
                    <figcaption class="mt-4 text-center text-sm leading-7 text-stone-600">{{ $caption }}</figcaption>
                @endif

                <p class="mt-3 text-center text-xs text-stone-400">
                    <a
                        href="{{ $link }}"
                        target="_blank"
                        rel="noopener noreferrer"
                        class="inline-flex items-center gap-1 font-semibold text-green-700 transition hover:text-green-800"
                    >
                        {{ __('ui.common.watch_video') }}
                        <x-site.icon name="external" class="h-3 w-3" />
                    </a>
                </p>
            </figure>
        </div>
    </section>
@endif
