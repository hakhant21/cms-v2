@php
    $items = is_array($data['items'] ?? null) ? $data['items'] : [];
    $title = filled($data['title'] ?? null) ? (string) $data['title'] : null;
@endphp

@if ($items !== [])
    <section class="scroll-mt-24 py-14 sm:py-20">
        <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
            @if ($title)
                <div class="flex flex-wrap items-end justify-between gap-4" data-reveal>
                    <h2 class="max-w-2xl text-3xl font-black tracking-tight text-stone-900 sm:text-4xl">{{ $title }}</h2>
                    <a href="{{ locale_path('/products') }}" class="inline-flex items-center gap-2 text-sm font-semibold text-green-700 transition hover:gap-3">
                        {{ __('ui.common.browse_all') }}
                        <x-site.icon name="arrow-right" class="h-4 w-4" />
                    </a>
                </div>
            @endif

            <div class="mt-10 grid gap-6 sm:grid-cols-2 lg:grid-cols-3" data-reveal-group>
                @foreach ($items as $item)
                    <article class="content-card" data-reveal>
                        <a href="{{ locale_path((string) $item['url']) }}" class="group flex h-full flex-col">
                            <div class="relative aspect-[4/3] overflow-hidden bg-stone-100">
                                @if (! empty($item['image_url']))
                                    <img
                                        src="{{ $item['image_url'] }}"
                                        alt="{{ $item['title'] }}"
                                        class="h-full w-full object-cover transition duration-700 group-hover:scale-105"
                                        loading="lazy"
                                        decoding="async"
                                    >
                                @else
                                    <div class="flex h-full w-full items-center justify-center bg-green-50 text-green-700">
                                        <x-site.icon name="leaf" class="h-12 w-12" />
                                    </div>
                                @endif
                            </div>

                            <div class="flex flex-1 flex-col p-6">
                                <h3 class="text-lg font-bold text-stone-900 transition group-hover:text-green-700">
                                    {{ $item['title'] }}
                                </h3>

                                @if (! empty($item['excerpt']))
                                    <p class="mt-2 line-clamp-3 text-sm leading-7 text-stone-600">{{ $item['excerpt'] }}</p>
                                @endif

                                <span class="mt-auto pt-4 inline-flex items-center gap-2 text-sm font-semibold text-green-700">
                                    {{ __('ui.common.view_details') }}
                                    <x-site.icon name="arrow-right" class="h-4 w-4" />
                                </span>
                            </div>
                        </a>
                    </article>
                @endforeach
            </div>
        </div>
    </section>
@endif
