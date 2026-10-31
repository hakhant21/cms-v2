@php
    use App\Support\GoogleMap;

    $title = filled($data['title'] ?? null) ? (string) $data['title'] : null;
    $map = GoogleMap::fromSettings(is_array($data) ? $data : []);
    $embed = $map->embedUrl();
    $external = $map->externalUrl();
    $address = trim((string) setting('company.address'));
@endphp

@if ($embed !== null)
    <section class="scroll-mt-24 py-12 sm:py-16">
        <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
            @if ($title)
                <h2 class="text-3xl font-black tracking-tight text-stone-900 sm:text-4xl" data-reveal>{{ $title }}</h2>
            @endif

            <div class="map-frame mt-6 h-[22rem] w-full ring-1 ring-stone-200 sm:h-[28rem]">
                <iframe
                    src="{{ $embed }}"
                    title="{{ $title ?: (setting('company.name') ?: __('ui.map.title')) }}"
                    loading="lazy"
                    decoding="async"
                    referrerpolicy="no-referrer-when-downgrade"
                    allowfullscreen
                ></iframe>
            </div>

            {{-- The embed is a still; this is the way into the real map, with
                 directions, photos and the live listing. --}}
            <div class="mt-4 flex flex-wrap items-center justify-between gap-3">
                @if ($address !== '')
                    <p class="inline-flex items-start gap-2 text-sm text-stone-600">
                        <x-site.icon name="map-pin" class="mt-0.5 h-4 w-4 shrink-0 text-[var(--site-primary)]" />
                        <span>{{ $address }}</span>
                    </p>
                @endif

                @if ($external !== null)
                    <a
                        href="{{ $external }}"
                        target="_blank"
                        rel="noopener noreferrer"
                        class="inline-flex items-center gap-2 text-sm font-semibold text-[var(--site-primary)] underline-offset-4 hover:underline"
                    >
                        {{ __('ui.map.open') }}
                        <x-site.icon name="arrow-right" class="h-4 w-4" />
                    </a>
                @endif
            </div>
        </div>
    </section>
@endif
