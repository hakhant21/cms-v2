@php
    use Illuminate\Support\Carbon;

    $text = fn (string $key): string => trim((string) ($data[$key] ?? ''));

    $farmer = $text('farmer_name');
    $location = $text('location');
    $quote = $text('quote');
    $photo = media_url(is_string($data['photo'] ?? null) ? $data['photo'] : null);

    $facts = array_values(array_filter([
        ['label' => 'Crop', 'value' => $text('crop')],
        ['label' => 'Variety', 'value' => $text('crop_variety')],
        ['label' => 'Acreage', 'value' => $text('acreage')],
        ['label' => 'Crops Grown', 'value' => $text('crops_grown')],
    ], fn (array $fact): bool => $fact['value'] !== ''));

    $timeline = array_values(array_filter([
        ['label' => 'Seed bed', 'date' => $text('seed_bed_date')],
        ['label' => 'Transplanting', 'date' => $text('transplanting_date')],
        ['label' => 'Picking', 'date' => $text('picking_date')],
    ], fn (array $step): bool => $step['date'] !== ''));

    $results = array_values(array_filter(array_map(
        fn (array $row): string => trim((string) ($row['value'] ?? '')),
        is_array($data['proven_results'] ?? null) ? $data['proven_results'] : [],
    )));

    $benefits = array_values(array_filter(array_map(
        fn (array $row): string => trim((string) ($row['value'] ?? '')),
        is_array($data['key_benefits'] ?? null) ? $data['key_benefits'] : [],
    )));

    /* Yields are written by editors ("1.2 t/acre"), so the bar width comes from
       the numeric part while the label keeps the full string. */
    $parseYield = function (string $value): array {
        if (preg_match('/^\s*([0-9]+(?:[.,][0-9]+)?)\s*(.*)$/u', $value, $matches) !== 1) {
            return ['number' => null, 'suffix' => $value];
        }

        return [
            'number' => (float) str_replace(',', '.', $matches[1]),
            'suffix' => trim($matches[2]),
        ];
    };

    $previous = $parseYield($text('previous_year_yield'));
    $ruma = $parseYield($text('ruma_field_yield'));
    $peak = max((float) ($previous['number'] ?? 0), (float) ($ruma['number'] ?? 0));
    $previousWidth = $peak > 0 && $previous['number'] !== null ? round(($previous['number'] / $peak) * 100, 1) : 0;
    $rumaWidth = $peak > 0 && $ruma['number'] !== null ? round(($ruma['number'] / $peak) * 100, 1) : 0;

    $formatDate = fn (string $date): string => Carbon::parse($date)->translatedFormat('M j, Y');
@endphp

<article class="pb-16">
    <header class="relative overflow-hidden bg-stone-950 pt-12 pb-16 text-white">
        <div class="pointer-events-none absolute -top-24 -right-16 h-72 w-72 rounded-full bg-lime-300/15 blur-3xl" aria-hidden="true"></div>

        <div class="relative mx-auto grid max-w-7xl items-center gap-10 px-4 sm:px-6 lg:grid-cols-[minmax(0,0.8fr)_minmax(0,1.2fr)] lg:px-8">
            <div data-reveal="left">
                {{-- The placeholder sits underneath, so a portrait that fails to
                     load leaves a quiet panel rather than a block of alt text. --}}
                <div class="relative aspect-[4/5] w-full overflow-hidden rounded-[2rem] bg-white/5 ring-1 ring-white/15">
                    <div class="flex h-full w-full items-center justify-center">
                        <x-site.icon name="leaf" class="h-16 w-16 text-lime-300" />
                    </div>

                    @if ($photo)
                        <img
                            src="{{ $photo }}"
                            alt="{{ $farmer }}"
                            class="absolute inset-0 h-full w-full object-cover"
                            loading="lazy"
                            decoding="async"
                            onerror="this.remove()"
                        >
                    @endif
                </div>
            </div>

            <div data-reveal="right">
                <p class="content-eyebrow text-lime-300">Field result</p>

                @if ($farmer !== '')
                    <h1 class="mt-4 text-4xl font-black tracking-tight sm:text-5xl">{{ $farmer }}</h1>
                @endif

                @if ($location !== '')
                    <p class="mt-2 inline-flex items-center gap-2 text-sm text-stone-300">
                        <x-site.icon name="map-pin" class="h-4 w-4 text-lime-300" />
                        {{ $location }}
                    </p>
                @endif

                @if ($quote !== '')
                    <blockquote class="mt-6 border-l-2 border-lime-300/60 pl-5 text-lg leading-8 text-stone-200">
                        &ldquo;{{ $quote }}&rdquo;
                    </blockquote>
                @endif

                @if ($facts !== [])
                    <dl class="mt-8 grid grid-cols-2 gap-4 sm:grid-cols-4" data-reveal-group>
                        @foreach ($facts as $fact)
                            <div class="rounded-2xl bg-white/5 p-4 ring-1 ring-white/10" data-reveal>
                                <dt class="text-[0.65rem] font-semibold tracking-[0.2em] text-stone-400 uppercase">{{ $fact['label'] }}</dt>
                                <dd class="mt-1 text-sm font-bold text-white">{{ $fact['value'] }}</dd>
                            </div>
                        @endforeach
                    </dl>
                @endif
            </div>
        </div>
    </header>

    <div class="mx-auto -mt-10 max-w-7xl px-4 sm:px-6 lg:px-8">
        <div class="grid gap-6 lg:grid-cols-3">
            @if ($timeline !== [])
                <section class="content-card p-6 lg:col-span-2" data-reveal>
                    <h2 class="text-xs font-bold tracking-[0.22em] text-green-700 uppercase">Season timeline</h2>

                    <ol class="mt-5 grid gap-4 sm:grid-cols-3">
                        @foreach ($timeline as $step)
                            <li class="relative rounded-2xl bg-green-50 p-4">
                                <span class="absolute -top-1.5 left-4 h-3 w-3 rounded-full bg-green-700 ring-4 ring-white"></span>
                                <p class="text-[0.65rem] font-semibold tracking-[0.18em] text-stone-500 uppercase">{{ $step['label'] }}</p>
                                <p class="mt-1 text-sm font-bold text-stone-900">{{ $formatDate($step['date']) }}</p>
                            </li>
                        @endforeach
                    </ol>
                </section>
            @endif

            @if ($text('field_challenge') !== '')
                <section class="content-card p-6" data-reveal>
                    <h2 class="text-xs font-bold tracking-[0.22em] text-green-700 uppercase">Field challenge</h2>
                    <p class="mt-3 text-sm leading-7 text-stone-600">{{ $text('field_challenge') }}</p>
                </section>
            @endif
        </div>

        @if ($previous['number'] !== null || $ruma['number'] !== null)
            <section class="content-card mt-6 p-6 sm:p-8" data-reveal>
                <h2 class="text-xs font-bold tracking-[0.22em] text-green-700 uppercase">Yield comparison</h2>

                <div class="mt-6 space-y-6">
                    @if ($previous['number'] !== null)
                        <div>
                            <div class="flex items-baseline justify-between gap-4">
                                <span class="text-sm font-semibold text-stone-600">Previous year</span>
                                <span class="text-lg font-black text-stone-900">
                                    <span data-count="{{ $previous['number'] }}" data-count-duration="1200">{{ $previous['number'] }}</span>
                                    {{ $previous['suffix'] }}
                                </span>
                            </div>
                            <div class="mt-2 h-2.5 overflow-hidden rounded-full bg-stone-100">
                                <div class="yield-bar bg-stone-400" style="--bar: {{ $previousWidth }}%"></div>
                            </div>
                        </div>
                    @endif

                    @if ($ruma['number'] !== null)
                        <div>
                            <div class="flex items-baseline justify-between gap-4">
                                <span class="text-sm font-semibold text-green-700">With Ruma</span>
                                <span class="text-lg font-black text-green-700">
                                    <span data-count="{{ $ruma['number'] }}" data-count-duration="1500">{{ $ruma['number'] }}</span>
                                    {{ $ruma['suffix'] }}
                                </span>
                            </div>
                            <div class="mt-2 h-2.5 overflow-hidden rounded-full bg-green-50">
                                <div class="yield-bar bg-green-700" style="--bar: {{ $rumaWidth }}%"></div>
                            </div>
                        </div>
                    @endif
                </div>
            </section>
        @endif

        <div class="mt-6 grid gap-6 md:grid-cols-2">
            @foreach ([['Proven results', $results], ['Key benefits', $benefits]] as [$heading, $list])
                @if ($list !== [])
                    <section class="content-card p-6 sm:p-7" data-reveal>
                        <h2 class="text-xs font-bold tracking-[0.22em] text-green-700 uppercase">{{ $heading }}</h2>
                        <ul class="mt-4 space-y-3">
                            @foreach ($list as $item)
                                <li class="flex items-start gap-3 text-sm leading-7 text-stone-700">
                                    <x-site.icon name="check-circle" class="mt-1 h-4 w-4 shrink-0 text-green-700" />
                                    <span>{{ $item }}</span>
                                </li>
                            @endforeach
                        </ul>
                    </section>
                @endif
            @endforeach
        </div>

        @if ($text('treatment') !== '' || $text('other_crop_details') !== '')
            <section class="content-card mt-6 p-6 sm:p-8" data-reveal>
                <div class="grid gap-8 sm:grid-cols-2">
                    @if ($text('treatment') !== '')
                        <div>
                            <h2 class="text-xs font-bold tracking-[0.22em] text-green-700 uppercase">Treatment</h2>
                            <p class="mt-3 text-sm leading-7 text-stone-600">{{ $text('treatment') }}</p>
                        </div>
                    @endif

                    @if ($text('other_crop_details') !== '')
                        <div>
                            <h2 class="text-xs font-bold tracking-[0.22em] text-green-700 uppercase">Other crops</h2>
                            <p class="mt-3 text-sm leading-7 text-stone-600">{{ $text('other_crop_details') }}</p>
                        </div>
                    @endif
                </div>
            </section>
        @endif
    </div>
</article>
