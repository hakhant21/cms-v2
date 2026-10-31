@php
    $pills = [];

    foreach ($section->rows($section->field('pills')) as $row) {
        $label = trim((string) ($row['value'] ?? ''));

        if ($label !== '') {
            $pills[] = ['label' => $label, 'url' => (string) ($row['url'] ?? '#')];
        }
    }
@endphp

<x-site.section-shell>
    <div class="soil-intro" data-reveal>
        @if ($section->value('eyebrow'))
            <p class="soil-intro__eyebrow">{{ $section->value('eyebrow') }}</p>
        @endif
        @if ($section->value('title'))
            <h2 class="soil-intro__title">{{ $section->value('title') }}</h2>
        @endif
        @if ($section->value('subtitle') || $section->value('paragraph'))
            <p class="soil-intro__subtitle">{{ $section->value('subtitle') ?: $section->value('paragraph') }}</p>
        @endif

        @if ($pills !== [])
            <div class="soil-intro__pills">
                @foreach ($pills as $pill)
                    <a href="{{ $pill['url'] }}" class="soil-intro__pill">{{ $pill['label'] }}</a>
                @endforeach
            </div>
        @endif
    </div>
</x-site.section-shell>
