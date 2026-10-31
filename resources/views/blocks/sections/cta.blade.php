@php
    $button = $section->button();
@endphp

<section class="bg-green-700 px-4 py-16 text-center text-white sm:py-20">
    <div class="mx-auto max-w-3xl" data-reveal>
        @if ($section->value('eyebrow'))
            <p class="text-xs font-semibold tracking-[0.25em] text-lime-300 uppercase">{{ $section->value('eyebrow') }}</p>
        @endif
        @if ($section->value('title'))
            <h2 class="mt-3 text-3xl font-bold text-balance sm:text-4xl">{{ $section->value('title') }}</h2>
        @endif
        @if ($section->value('paragraph'))
            <p class="mt-4 leading-8 text-green-100">{{ $section->value('paragraph') }}</p>
        @endif
        @if ($button)
            <a
                href="{{ locale_path($button['url']) }}"
                class="btn-glow mt-8 inline-flex items-center gap-2 rounded-full bg-white px-7 py-3 text-sm font-semibold text-green-700 transition hover:bg-green-50"
            >
                {{ $button['label'] }}
                <x-site.icon name="arrow-right" class="h-4 w-4" />
            </a>
        @endif
    </div>
</section>
