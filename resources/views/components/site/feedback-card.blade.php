@props(['item'])

@php
    $quote = trim((string) ($item['data']['quote'] ?? $item['excerpt'] ?? ''));
    $farmer = trim((string) ($item['data']['farmer_name'] ?? $item['title']));
    $location = trim((string) ($item['data']['location'] ?? ''));
    $crops = trim((string) ($item['data']['crops_grown'] ?? ''));
    $photo = $item['data']['photo'] ?? $item['image_url'] ?? null;

    $place = trim($location.($location !== '' && $crops !== '' ? ' · ' : '').$crops);
    $href = (string) ($item['url'] ?? '');
@endphp

{{-- The card is a column so the portrait and the name always sit on the same
     line at the bottom, whether the quote above is two lines or ten. --}}
<figure {{ $attributes->class(['content-card flex h-full flex-col p-7']) }}>
    <x-site.icon name="quote" class="h-8 w-8 text-lime-500" />

    @if ($quote !== '')
        <blockquote class="mt-4 flex-1 text-base leading-8 text-stone-700">
            &ldquo;{{ $quote }}&rdquo;
        </blockquote>
    @else
        <div class="flex-1"></div>
    @endif

    <figcaption class="mt-6 flex items-center gap-3 border-t border-stone-100 pt-5">
        <x-site.avatar
            :src="$photo ? media_url(is_string($photo) ? $photo : null) : null"
            :name="$farmer"
        />

        <div class="min-w-0">
            <p class="truncate text-sm font-bold text-stone-900">{{ $farmer }}</p>
            @if ($place !== '')
                <p class="truncate text-xs text-stone-500">{{ $place }}</p>
            @endif
        </div>

        @if ($href !== '')
            <a
                href="{{ locale_path($href) }}"
                class="ml-auto inline-flex shrink-0 items-center gap-1 text-xs font-semibold text-green-700 transition hover:gap-2"
                aria-label="{{ __('ui.common.view_details') }} — {{ $farmer }}"
            >
                <x-site.icon name="arrow-right" class="h-3.5 w-3.5" />
            </a>
        @endif
    </figcaption>
</figure>
