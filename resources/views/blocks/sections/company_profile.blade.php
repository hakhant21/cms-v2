@php
    $statements = $section->statements();

    $cards = [
        ['index' => '01', 'label' => 'PROFILE', 'class' => 'company-profile__card--profile', 'text' => $statements['profile']],
        ['index' => '02', 'label' => 'MISSION', 'class' => 'company-profile__card--mission', 'text' => $statements['mission']],
        ['index' => '03', 'label' => 'VISION', 'class' => 'company-profile__card--vision', 'text' => $statements['vision']],
    ];
@endphp

<section class="company-profile">
    <div class="company-profile__heading" data-reveal>
        <span class="company-profile__eyebrow">{{ $section->value('eyebrow') ?: __('ui.about.overview_eyebrow') }}</span>
        <h2>{{ $section->value('title') ?: __('ui.nav.about') }}</h2>
    </div>

    <div class="company-profile__cards" data-reveal-group>
        @foreach ($cards as $position => $card)
            @continue ($card['text'] === null)
            <article class="company-profile__card {{ $card['class'] }}" data-reveal style="--reveal-delay: {{ $position * 110 }}ms">
                <span class="company-profile__index">{{ $card['index'] }} / {{ $card['label'] }}</span>
                <p>{{ $card['text'] }}</p>
            </article>
        @endforeach
    </div>
</section>
