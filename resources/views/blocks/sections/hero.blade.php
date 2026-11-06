@php
    $fields = $section->fields();
    $globeCaption = $section->globeCaption();
@endphp

{{-- The backdrop belongs to `.site-hero` in the stylesheet: one dark green
     panel, so the copy, the globe and its caption all sit on the same tone. --}}
<section class="site-editorial-hero site-hero relative text-white">
    <div class="site-hero-pattern pointer-events-none absolute inset-0 opacity-30" aria-hidden="true"></div>

    <div class="relative mx-auto grid max-w-7xl items-center gap-10 px-4 py-14 sm:px-6 sm:py-20 lg:px-8 xl:grid-cols-[minmax(0,1fr)_minmax(19rem,0.72fr)] xl:gap-12">
        <div class="min-w-0" data-reveal-group>
            @foreach ($fields as $position => $field)
                @continue (($field['type'] ?? '') === 'globe')
                <div class="mt-6 first:mt-0" data-reveal style="--reveal-delay: {{ min($position, 5) * 90 }}ms">
                    @if (($field['type'] ?? '') === 'title')
                        <h1 class="text-3xl font-bold text-balance text-white sm:text-4xl lg:text-5xl">
                            {{ $field['value'] ?? '' }}
                        </h1>
                    @else
                        <x-site.section-field :field="$field" tone="hero" />
                    @endif
                </div>
            @endforeach
        </div>

        <div class="min-w-0">
            <x-site.earth-connection :caption="$globeCaption" />
        </div>
    </div>
</section>
