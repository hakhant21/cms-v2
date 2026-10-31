@php
    $checklist = $section->texts('checklist');
    $callout = $section->field('callout');
    $calloutItems = $section->texts('callout');
    $stats = $section->stats();
    $anchor = $section->anchor();
@endphp

<section @if ($anchor !== '') id="{{ $anchor }}" @endif class="soil-dimension scroll-mt-24">
    <div class="soil-dimension__grid">
        <div class="soil-dimension__copy">
            <div data-reveal>
                @if ($section->value('eyebrow'))
                    <p class="soil-dimension__eyebrow">{{ $section->value('eyebrow') }}</p>
                @endif
                @if ($section->value('title'))
                    <h2 class="soil-dimension__title">{{ $section->value('title') }}</h2>
                @endif
                @if ($section->value('paragraph'))
                    <p class="soil-dimension__intro">{{ $section->value('paragraph') }}</p>
                @endif
            </div>

            @if ($checklist !== [])
                <div class="soil-dimension__list" data-reveal>
                    <x-site.checklist :items="$checklist" :columns="1" />
                </div>
            @endif
        </div>

        <div class="soil-dimension__aside">
            @if ($callout !== null || $calloutItems !== [])
                <div class="callout-card" data-reveal="right">
                    <div class="callout-card__head">
                        <span class="callout-card__icon" aria-hidden="true">
                            <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <path d="M3 21c3 0 7-1 7-8V5a2 2 0 0 0-2-2H5a2 2 0 0 0-2 2v6a2 2 0 0 0 2 2h2" />
                                <path d="M15 21c3 0 7-1 7-8V5a2 2 0 0 0-2-2h-3a2 2 0 0 0-2 2v6a2 2 0 0 0 2 2h2" />
                            </svg>
                        </span>
                        @if (! empty($callout['value']))
                            <h3 class="callout-card__title">{{ $callout['value'] }}</h3>
                        @endif
                    </div>
                    @if (! empty($callout['text']))
                        <p class="callout-card__text">{{ $callout['text'] }}</p>
                    @endif
                    @if ($calloutItems !== [])
                        <ul class="callout-card__items">
                            @foreach ($calloutItems as $item)
                                <li>{{ $item }}</li>
                            @endforeach
                        </ul>
                    @endif
                </div>
            @endif

            <x-site.soil-infographic :variant="$section->variant()" :stats="$stats" />
        </div>
    </div>
</section>
