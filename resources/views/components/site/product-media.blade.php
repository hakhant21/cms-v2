@props(['page', 'title' => null])

@php
    $featured = $page->featuredImage;
    $gallery = $page->galleryImages;

    // One picture leads the panel: the featured image when the editor chose
    // one, otherwise the first photo in the gallery. The rest become
    // thumbnails, so a product with a single photo still looks complete.
    $cover = $featured ?? $gallery->first();
    $thumbnails = $cover === null
        ? collect()
        : $gallery->reject(fn ($image): bool => $image->getKey() === $cover->getKey())->values();

    $heading = $title ?: $page->localizedTitle();
    $excerpt = $page->localizedExcerpt();

    // The main picture and the thumbnails are one set, so the arrows in the
    // lightbox walk through every photo of the product.
    $lightboxSet = 'product-'.$page->getKey();
@endphp

@if ($cover !== null)
    <section class="py-10 sm:py-14" data-reveal>
        <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
            <div class="grid items-start gap-8 lg:grid-cols-[minmax(0,1.05fr)_minmax(0,0.95fr)] lg:gap-14">
                <figure class="relative">
                    {{-- The glow is inset on phones: a bleeding shadow was what
                         made product pages scroll sideways. --}}
                    <span class="absolute inset-2 -z-10 rounded-[2.5rem] bg-lime-300/25 blur-2xl" aria-hidden="true"></span>

                    <a
                        href="{{ $cover->url() }}"
                        data-lightbox
                        data-lightbox-set="{{ $lightboxSet }}"
                        data-lightbox-caption="{{ $cover->altText() ?: $heading }}"
                        class="group block overflow-hidden rounded-3xl bg-white shadow-xl ring-1 ring-stone-900/5"
                    >
                        <img
                            src="{{ $cover->url() }}"
                            alt="{{ $cover->altText() }}"
                            class="mx-auto h-64 w-full cursor-zoom-in object-contain p-6 transition duration-700 group-hover:scale-[1.03] sm:h-80 lg:h-[26rem]"
                            loading="eager"
                            decoding="async"
                        >
                    </a>
                </figure>

                <div class="min-w-0">
                    <p class="content-eyebrow">{{ __('ui.nav.products') }}</p>

                    <h1 class="mt-3 text-3xl font-black tracking-tight text-balance text-stone-900 sm:text-4xl lg:text-5xl">
                        {{ $heading }}
                    </h1>

                    @if (filled($excerpt))
                        <p class="mt-4 max-w-prose text-lg leading-8 text-stone-600">{{ $excerpt }}</p>
                    @endif

                    @if ($thumbnails->isNotEmpty())
                        <div class="mt-7">
                            <p class="text-xs font-semibold tracking-[0.18em] text-stone-500 uppercase">
                                {{ __('ui.products.more_photos') }}
                            </p>
                            <div class="mt-3 grid grid-cols-3 gap-3" data-reveal-group>
                                @foreach ($thumbnails as $image)
                                    <a
                                        href="{{ $image->url() }}"
                                        data-lightbox
                                        data-lightbox-set="{{ $lightboxSet }}"
                                        data-lightbox-caption="{{ $image->altText() ?: $heading }}"
                                        class="group block overflow-hidden rounded-2xl bg-white ring-1 ring-stone-200 transition hover:ring-green-700"
                                    >
                                        <img
                                            src="{{ $image->url() }}"
                                            alt="{{ $image->altText() }}"
                                            class="h-24 w-full cursor-zoom-in object-contain p-2 transition duration-500 group-hover:scale-105"
                                            loading="lazy"
                                            decoding="async"
                                        >
                                    </a>
                                @endforeach
                            </div>
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </section>
@endif
