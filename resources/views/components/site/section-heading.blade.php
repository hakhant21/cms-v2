@props([
    'section',
    'align' => 'left',
    'tone' => 'light',
    'width' => '3xl',
])

@php
    $dark = $tone === 'dark';

    $eyebrow = $section->value('eyebrow');
    $title = $section->value('title');
    $subtitle = $section->value('subtitle');
    $paragraph = $section->value('paragraph');

    $widthClass = match ($width) {
        '2xl' => 'max-w-2xl',
        '4xl' => 'max-w-4xl',
        default => 'max-w-3xl',
    };
@endphp

@if ($eyebrow || $title || $subtitle || $paragraph)
    <div @class([
        $widthClass,
        'mx-auto text-center' => $align === 'center',
    ]) data-reveal>
        @if ($eyebrow)
            <p @class(['content-eyebrow', 'justify-center' => $align === 'center'])>{{ $eyebrow }}</p>
        @endif

        @if ($title)
            <h2 @class([
                'mt-4 font-black tracking-tight sm:text-4xl lg:text-[2.75rem] lg:leading-[1.1]',
                'text-3xl' => true,
                'text-white' => $dark,
                'text-stone-900' => ! $dark,
            ])>
                {{ $title }}
            </h2>
        @endif

        @if ($subtitle)
            <p @class([
                'mt-4 text-lg font-medium',
                'text-lime-100/90' => $dark,
                'text-stone-600' => ! $dark,
            ])>
                {{ $subtitle }}
            </p>
        @endif

        @if ($paragraph)
            <p @class([
                'mt-4 text-base leading-8',
                'text-stone-100/80' => $dark,
                'text-stone-700' => ! $dark,
            ])>
                {{ $paragraph }}
            </p>
        @endif
    </div>
@endif
