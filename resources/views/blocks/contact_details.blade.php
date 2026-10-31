@php
    $title = filled($data['title'] ?? null) ? (string) $data['title'] : null;

    $details = array_values(array_filter([
        [
            'icon' => 'mail',
            'label' => __('ui.common.email'),
            'value' => setting('company.email'),
            'href' => setting('company.email') ? 'mailto:'.setting('company.email') : null,
        ],
        [
            'icon' => 'phone',
            'label' => __('ui.common.phone'),
            'value' => trim((string) setting('company.phone').' '.setting('company.phone_secondary')),
            'href' => setting('company.phone') ? 'tel:'.preg_replace('/[^0-9+]/', '', (string) setting('company.phone')) : null,
        ],
        [
            'icon' => 'map-pin',
            'label' => __('ui.footer.address'),
            'value' => setting('company.address'),
            'href' => null,
        ],
        [
            'icon' => 'clock',
            'label' => __('ui.common.business_hours'),
            'value' => setting('company.office_hours'),
            'href' => null,
        ],
    ], fn (array $detail): bool => filled($detail['value'])));

    $social = array_filter([
        'facebook' => setting('social.facebook'),
        'instagram' => setting('social.instagram'),
        'youtube' => setting('social.youtube'),
        'tiktok' => setting('social.tiktok'),
    ]);
@endphp

@if ($details !== [] || $social !== [])
    <section class="scroll-mt-24 py-12 sm:py-16">
        <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
            @if ($title)
                <h2 class="text-3xl font-black tracking-tight text-stone-900 sm:text-4xl" data-reveal>{{ $title }}</h2>
            @endif

            <div class="mt-8 grid gap-4 sm:grid-cols-2 lg:grid-cols-4" data-reveal-group>
                @foreach ($details as $detail)
                    <div class="contact-tile" data-reveal>
                        <span class="contact-tile__icon">
                            <x-site.icon :name="$detail['icon']" class="h-5 w-5" />
                        </span>

                        <div class="min-w-0">
                            <p class="text-[0.68rem] font-semibold tracking-[0.18em] text-stone-500 uppercase">{{ $detail['label'] }}</p>
                            @if ($detail['href'])
                                <a href="{{ $detail['href'] }}" class="mt-1 block text-sm font-semibold break-words text-stone-900 transition hover:text-green-700">
                                    {{ $detail['value'] }}
                                </a>
                            @else
                                <p class="mt-1 text-sm font-semibold text-stone-900">{{ $detail['value'] }}</p>
                            @endif
                        </div>
                    </div>
                @endforeach
            </div>

            @if ($social !== [])
                <div class="mt-8 flex flex-wrap items-center gap-3" data-reveal>
                    <span class="text-xs font-semibold tracking-[0.18em] text-stone-500 uppercase">Follow</span>
                    @foreach ($social as $network => $url)
                        <a
                            href="{{ $url }}"
                            target="_blank"
                            rel="noopener noreferrer"
                            class="inline-flex h-10 w-10 items-center justify-center rounded-full border border-stone-200 text-stone-600 transition hover:border-green-700 hover:text-green-700"
                            aria-label="{{ ucfirst($network) }}"
                        >
                            <x-site.icon :name="$network" class="h-4 w-4" />
                        </a>
                    @endforeach
                </div>
            @endif
        </div>
    </section>
@endif
