@props(['variant' => 'structure', 'stats' => []])

@php
    // Each dimension gets its own motif; the numbers themselves are content.
    $motifs = [
        'structure' => ['layers', 'water', 'sprout'],
        'nutrient' => ['flask', 'trend', 'leaf'],
        'biological' => ['bug', 'sprout', 'leaf'],
        'before_after' => ['swap', 'trend', 'leaf'],
    ];

    $icons = $motifs[$variant] ?? $motifs['structure'];

    $shape = [
        'layers' => '<path d="m12 2 9 5-9 5-9-5 9-5Z"/><path d="m3 12 9 5 9-5"/><path d="m3 17 9 5 9-5"/>',
        'water' => '<path d="M12 2.7 6.5 9.4a7 7 0 1 0 11 0Z"/>',
        'sprout' => '<path d="M12 20v-8"/><path d="M12 12c0-3.3 2.7-6 6-6 0 3.3-2.7 6-6 6Z"/><path d="M12 15c0-2.8-2.2-5-5-5 0 2.8 2.2 5 5 5Z"/>',
        'flask' => '<path d="M9 3h6"/><path d="M10 3v6L5 19a2 2 0 0 0 1.8 3h10.4A2 2 0 0 0 19 19l-5-10V3"/>',
        'trend' => '<path d="m3 17 6-6 4 4 8-8"/><path d="M17 7h4v4"/>',
        'leaf' => '<path d="M11 20A7 7 0 0 1 4 13c0-6 8-9 16-9 0 8-3 16-9 16Z"/><path d="M4 21c1.5-5 5-8 10-9"/>',
        'bug' => '<circle cx="12" cy="12" r="6"/><path d="M12 6V3M9 8 6 5M15 8l3-3M6 13H3M21 13h-3M9 17l-3 3M15 17l3 3"/>',
        'swap' => '<path d="m17 3 4 4-4 4"/><path d="M21 7H7"/><path d="m7 21-4-4 4-4"/><path d="M3 17h14"/>',
    ];
@endphp

<div class="soil-infographic soil-infographic--{{ $variant }}" data-reveal>
    <div class="soil-infographic__motif" aria-hidden="true">
        @foreach ($icons as $icon)
            <svg class="soil-infographic__motif-icon" viewBox="0 0 24 24" width="22" height="22" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                {!! $shape[$icon] ?? $shape['leaf'] !!}
            </svg>
        @endforeach
    </div>

    @if ($stats !== [])
        <dl class="soil-infographic__stats">
            @foreach ($stats as $position => $stat)
                <div class="soil-infographic__stat" data-reveal style="--reveal-delay: {{ $position * 90 }}ms">
                    <dd class="soil-infographic__value">{{ $stat['value'] }}</dd>
                    <dt class="soil-infographic__label">{{ $stat['title'] }}</dt>
                </div>
            @endforeach
        </dl>
    @endif
</div>
