@php
    $button = $section->button();
@endphp

<section class="cta-lime">
    <div class="cta-lime__inner" data-reveal>
        @if ($section->value('title'))
            <h2 class="cta-lime__title">{{ $section->value('title') }}</h2>
        @endif
        @if ($section->value('paragraph'))
            <p class="cta-lime__text">{{ $section->value('paragraph') }}</p>
        @endif
        @if ($button)
            <a href="{{ locale_path($button['url']) }}" class="cta-lime__button">
                {{ $button['label'] }}
                <x-site.icon name="arrow-right" class="h-4 w-4" />
            </a>
        @endif
    </div>
</section>
