@php
    $milestones = $section->milestones();
@endphp

<section class="farm-journey" aria-label="{{ $section->value('title') ?: __('ui.nav.about') }}" data-journey data-journey-interval="4200">
    <div class="farm-journey__copy" data-reveal>
        @if ($section->value('eyebrow'))
            <p class="farm-journey__eyebrow">{{ $section->value('eyebrow') }}</p>
        @endif
        <h2>{{ $section->value('title') ?: __('ui.about.timeline_title') }}</h2>
        <p class="farm-journey__intro">{{ $section->value('subtitle') ?: $section->value('paragraph') ?: __('ui.about.timeline_text') }}</p>
    </div>

    <div class="farm-journey__track" data-journey-track>
        {{-- The road winds between the year cards in two-column zigzags, and
             the tractor drives that very path from one year to the next. --}}
        <svg class="farm-journey__road" aria-hidden="true" data-journey-road>
            <path class="farm-journey__road-base" data-journey-road-path></path>
            <path class="farm-journey__road-centre" data-journey-road-centre></path>
        </svg>

        <div class="farm-journey__tractor" data-journey-tractor>
            <svg class="farm-journey__tractor-svg" viewBox="0 0 78 60" role="presentation">
                <rect x="19" y="20" width="3" height="10" rx="1.5" fill="var(--site-ink)" />
                <rect x="10" y="30" width="44" height="14" rx="3" fill="var(--site-primary)" />
                <path d="M30 30 L34 15 H52 L56 30 Z" fill="var(--site-primary-dark)" />
                <rect x="38" y="18" width="12" height="9" rx="1.5" fill="var(--site-accent-soft)" />
                <circle cx="45" cy="24" r="3" fill="var(--site-surface)" />
                <path d="M2 44 a16 16 0 0 1 32 0 Z" fill="var(--site-primary-dark)" />
                <g class="farm-journey__wheel">
                    <circle cx="18" cy="44" r="14" fill="var(--site-ink)" />
                    <circle cx="18" cy="44" r="7" fill="var(--site-accent-soft)" />
                    <rect x="17" y="31" width="2" height="26" fill="var(--site-primary)" />
                    <rect x="5" y="43" width="26" height="2" fill="var(--site-primary)" />
                </g>
                <g class="farm-journey__wheel">
                    <circle cx="60" cy="47" r="9" fill="var(--site-ink)" />
                    <circle cx="60" cy="47" r="4" fill="var(--site-accent-soft)" />
                    <rect x="59" y="38" width="2" height="18" fill="var(--site-primary)" />
                    <rect x="51" y="46" width="18" height="2" fill="var(--site-primary)" />
                </g>
            </svg>
        </div>

        {{-- Two years per row, snaking: the second row runs right to left, so
             the road is one continuous zigzag from the first year to the last. --}}
        <ol class="farm-journey__stops">
            @foreach ($milestones as $index => $milestone)
                @php
                    $row = intdiv($index, 2);
                    $col = $row % 2 === 0 ? ($index % 2) + 1 : 2 - ($index % 2);
                @endphp
                <li
                    class="farm-journey__stop {{ $index === 0 ? 'is-active' : '' }}"
                    style="--stop-col: {{ $col }}"
                    data-journey-stop
                    data-year="{{ $milestone['year'] }}"
                    data-text="{{ $milestone['text'] }}"
                    data-reveal
                >
                    <button type="button" class="farm-journey__card" data-journey-jump>
                        <span class="farm-journey__head">
                            <span class="farm-journey__dot" aria-hidden="true"></span>
                            <strong class="farm-journey__year">{{ $milestone['year'] }}</strong>
                        </span>
                        <span class="farm-journey__text">{{ $milestone['text'] }}</span>
                    </button>
                </li>
            @endforeach
        </ol>
    </div>

    <p class="farm-journey__live" aria-live="polite" data-journey-live></p>
</section>
