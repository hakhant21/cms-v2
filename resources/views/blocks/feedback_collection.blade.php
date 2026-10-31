@php
    $items = is_array($data['items'] ?? null) ? $data['items'] : [];
    $title = filled($data['title'] ?? null) ? (string) $data['title'] : null;

    $display = ($data['display'] ?? 'carousel') === 'grid' ? 'grid' : 'carousel';
    $perView = max(1, min(3, (int) ($data['per_view'] ?? 2)));
    // Stored in seconds because that is what an editor thinks in; the script
    // works in milliseconds.
    $interval = max(1, min(20, (int) ($data['interval'] ?? 2))) * 1000;
@endphp

@if ($items !== [])
    <section class="scroll-mt-24 bg-green-50 py-14 sm:py-20">
        <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
            @if ($title)
                <div class="max-w-3xl" data-reveal>
                    <p class="content-eyebrow">Testimonials</p>
                    <h2 class="mt-4 text-3xl font-black tracking-tight text-stone-900 sm:text-4xl">{{ $title }}</h2>
                </div>
            @endif

            @if ($display === 'grid')
                <div class="mt-10 grid gap-6 md:grid-cols-2 lg:grid-cols-3" data-reveal-group>
                    @foreach ($items as $item)
                        <x-site.feedback-card :item="$item" data-reveal />
                    @endforeach
                </div>
            @else
                <div
                    class="mt-10"
                    data-carousel
                    data-carousel-interval="{{ $interval }}"
                    data-carousel-per-view="{{ $perView }}"
                >
                    <div class="carousel-viewport" data-reveal>
                        <div
                            class="carousel-track"
                            data-carousel-track
                            style="--carousel-per-view: {{ $perView }}"
                        >
                            @foreach ($items as $item)
                                <div class="carousel-slide" data-carousel-slide>
                                    <x-site.feedback-card :item="$item" />
                                </div>
                            @endforeach
                        </div>
                    </div>

                    @if (count($items) > 1)
                        <div class="mt-7 flex items-center justify-center gap-4">
                            <button
                                type="button"
                                class="carousel-arrow"
                                data-carousel-prev
                                aria-label="{{ __('ui.common.previous') }}"
                            >
                                <x-site.icon name="arrow-right" class="h-4 w-4 rotate-180" />
                            </button>

                            <div class="flex items-center gap-1.5" data-carousel-dots></div>

                            <button
                                type="button"
                                class="carousel-arrow"
                                data-carousel-next
                                aria-label="{{ __('ui.common.next') }}"
                            >
                                <x-site.icon name="arrow-right" class="h-4 w-4" />
                            </button>
                        </div>
                    @endif
                </div>
            @endif
        </div>
    </section>
@endif
