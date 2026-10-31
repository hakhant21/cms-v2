@php
    $title = filled($data['title'] ?? null) ? (string) $data['title'] : null;

    // Offices live in Settings, entered once and shown in every language. The
    // labels below come from the lang files, so only the data itself is stored.
    $offices = array_values(array_filter(
        setting_json('company.offices'),
        fn (mixed $office): bool => is_array($office)
            && (filled($office['name'] ?? null) || filled($office['address'] ?? null)),
    ));

    $offices = array_map(function (array $office): array {
        $email = $office['email'] ?? null;
        $phone = $office['phone'] ?? null;

        $rows = array_values(array_filter([
            ['icon' => 'map-pin', 'label' => __('ui.footer.address'), 'value' => $office['address'] ?? null, 'href' => null, 'multiline' => true],
            ['icon' => 'document', 'label' => __('ui.contact.tax_id'), 'value' => $office['tax_id'] ?? null, 'href' => null],
            ['icon' => 'mail', 'label' => __('ui.common.email'), 'value' => $email, 'href' => filled($email) ? 'mailto:'.$email : null],
            ['icon' => 'phone', 'label' => __('ui.common.phone'), 'value' => $phone, 'href' => filled($phone) ? 'tel:'.preg_replace('/[^0-9+]/', '', (string) $phone) : null],
        ], fn (array $row): bool => filled($row['value'])));

        return ['name' => $office['name'] ?? '', 'rows' => $rows];
    }, $offices);

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
        // The single address tile stands in only until offices are configured;
        // once they are, each office carries its own address below.
        $offices === [] ? [
            'icon' => 'map-pin',
            'label' => __('ui.footer.address'),
            'value' => setting('company.address'),
            'href' => null,
        ] : null,
        [
            'icon' => 'clock',
            'label' => __('ui.common.business_hours'),
            'value' => setting('company.office_hours'),
            'href' => null,
        ],
    ], fn (?array $detail): bool => $detail !== null && filled($detail['value'])));

    $social = array_filter([
        'facebook' => setting('social.facebook'),
        'instagram' => setting('social.instagram'),
        'youtube' => setting('social.youtube'),
        'tiktok' => setting('social.tiktok'),
    ]);
@endphp

@if ($details !== [] || $offices !== [] || $social !== [])
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

            @if ($offices !== [])
                <div class="mt-10" data-reveal>
                    <p class="max-w-2xl text-sm text-stone-600">{{ __('ui.contact.intro') }}</p>
                    <h3 class="mt-3 text-xl font-bold tracking-tight text-stone-900">{{ __('ui.contact.offices_title') }}</h3>

                    <div class="mt-5 grid gap-4 md:grid-cols-2" data-reveal-group>
                        @foreach ($offices as $office)
                            <div class="rounded-2xl border border-stone-200 bg-white p-6 shadow-sm" data-reveal>
                                <h4 class="text-base font-bold text-stone-900">{{ $office['name'] }}</h4>

                                <dl class="mt-4 space-y-3">
                                    @foreach ($office['rows'] as $row)
                                        <div class="flex gap-3">
                                            <dt class="mt-0.5 shrink-0 text-green-700">
                                                <x-site.icon :name="$row['icon']" class="h-4 w-4" />
                                                <span class="sr-only">{{ $row['label'] }}</span>
                                            </dt>
                                            <dd class="min-w-0">
                                                <span class="block text-[0.68rem] font-semibold tracking-[0.18em] text-stone-500 uppercase">{{ $row['label'] }}</span>
                                                @if ($row['href'])
                                                    <a href="{{ $row['href'] }}" class="mt-0.5 block text-sm font-semibold break-words text-stone-900 transition hover:text-green-700">{{ $row['value'] }}</a>
                                                @elseif (! empty($row['multiline']))
                                                    <p class="mt-0.5 text-sm font-semibold text-stone-900">{!! nl2br(e($row['value'])) !!}</p>
                                                @else
                                                    <p class="mt-0.5 text-sm font-semibold break-words text-stone-900">{{ $row['value'] }}</p>
                                                @endif
                                            </dd>
                                        </div>
                                    @endforeach
                                </dl>
                            </div>
                        @endforeach
                    </div>
                </div>
            @endif

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
