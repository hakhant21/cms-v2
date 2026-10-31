@props(['images' => [], 'alt' => '', 'group' => null])

@if ($images !== [])
    {{-- Clicking a picture opens it in the shared lightbox instead of a new
         browser tab, so a visitor comparing lab reports never loses the page.
         The href stays for a visitor without JavaScript. --}}
    <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3" data-reveal-group data-lightbox-gallery>
        @foreach ($images as $image)
            <a
                href="{{ $image['src'] }}"
                @if ($group) data-lightbox-set="{{ $group }}" @endif
                data-lightbox
                data-lightbox-caption="{{ $image['alt'] ?: $alt }}"
                class="group relative block overflow-hidden rounded-2xl shadow-sm ring-1 ring-stone-200"
                data-reveal="zoom"
            >
                <img
                    src="{{ $image['src'] }}"
                    alt="{{ $image['alt'] ?: $alt }}"
                    class="h-52 w-full cursor-zoom-in object-cover transition duration-700 group-hover:scale-105"
                    loading="lazy"
                    decoding="async"
                >
            </a>
        @endforeach
    </div>
@endif
