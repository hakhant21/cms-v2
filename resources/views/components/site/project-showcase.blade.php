@props(['page', 'block'])

@php
    use App\Models\Page;
    use App\Support\ProjectBrief;

    $brief = ProjectBrief::fromBlock(is_array($block) ? $block : []);
    $title = $page->localizedTitle();

    // Project photos are studio shots on a white background, so the picture is
    // contained on a tinted panel rather than cropped to fill a box: cropping
    // a bottle to a square cut its label and left a wall of empty white.
    $image = $page->featuredImage ?? $page->galleryImages->first();
    $alt = $image?->alt ?: $title;
    $lightboxSet = 'project-'.$page->getKey();

    // The listing and contact pages are editor-owned, so the trail and the
    // closing button only link to them while they are actually published.
    $listing = Page::pathExists('/our-next-projects') ? '/our-next-projects' : null;
    $contact = Page::pathExists('/contact') ? '/contact' : null;

    $siblings = Page::query()
        ->published()
        ->ofKind(Page::KIND_PROJECT)
        ->whereKeyNot($page->getKey())
        ->ordered()
        ->with(['featuredImage', 'translations'])
        ->limit(3)
        ->get();
@endphp

{{-- Breadcrumb: a project used to open straight into its heading, with no way
     back to the roadmap it belongs to. --}}
<nav aria-label="Breadcrumb" class="mx-auto max-w-7xl px-4 pt-6 sm:px-6 lg:px-8" data-reveal>
    <ol class="flex flex-wrap items-center gap-x-2 gap-y-1 text-xs font-medium text-stone-500">
        <li>
            <a href="{{ locale_path('/') }}" wire:navigate class="transition hover:text-green-700">{{ __('ui.nav.home_page') }}</a>
        </li>
        <li aria-hidden="true" class="text-stone-300">/</li>
        @if ($listing !== null)
            <li>
                <a href="{{ locale_path($listing) }}" wire:navigate class="transition hover:text-green-700">{{ __('ui.nav.next_projects') }}</a>
            </li>
            <li aria-hidden="true" class="text-stone-300">/</li>
        @endif
        <li aria-current="page" class="line-clamp-1 min-w-0 font-semibold text-stone-700">{{ $title }}</li>
    </ol>
</nav>

<section class="relative overflow-hidden py-10 sm:py-14">
    <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
        <div @class([
            'grid items-center gap-10 lg:gap-16',
            'lg:grid-cols-[minmax(0,0.9fr)_minmax(0,1.1fr)]' => $image !== null,
        ])>
            @if ($image !== null)
                <figure class="relative order-2 lg:order-1" data-reveal="left">
                    <span class="absolute inset-4 -z-10 rounded-[3rem] bg-lime-300/30 blur-3xl" aria-hidden="true"></span>

                    <a
                        href="{{ $image->url() }}"
                        data-lightbox
                        data-lightbox-set="{{ $lightboxSet }}"
                        data-lightbox-caption="{{ $alt }}"
                        class="group block overflow-hidden rounded-[2rem] bg-gradient-to-br from-lime-50 via-white to-green-50/70 shadow-xl ring-1 ring-stone-900/5"
                    >
                        <img
                            src="{{ $image->url() }}"
                            alt="{{ $alt }}"
                            @if ($image->width && $image->height) width="{{ $image->width }}" height="{{ $image->height }}" @endif
                            class="aspect-square w-full cursor-zoom-in object-contain p-8 transition duration-700 group-hover:scale-[1.04] sm:p-10"
                            loading="eager"
                            decoding="async"
                        >
                    </a>

                    <span class="absolute top-4 left-4 inline-flex items-center gap-2 rounded-full bg-stone-950/75 px-4 py-2 text-xs font-semibold tracking-[0.14em] text-lime-200 uppercase backdrop-blur">
                        <x-site.icon name="leaf" class="h-3.5 w-3.5" />
                        {{ __('ui.nav.next_projects') }}
                    </span>
                </figure>
            @endif

            <div class="order-1 min-w-0 lg:order-2" data-reveal="right">
                <p class="content-eyebrow">{{ __('ui.next_projects.eyebrow') }}</p>

                <h1 class="mt-4 text-3xl font-black tracking-tight text-balance text-stone-900 sm:text-4xl lg:text-[2.9rem] lg:leading-[1.08]">
                    {{ $title }}
                </h1>

                @if (filled($brief->lead))
                    <p class="mt-5 max-w-prose text-lg leading-8 text-stone-600">{{ $brief->lead }}</p>
                @endif
            </div>
        </div>
    </div>
</section>

@if ($brief->highlights !== [])
    {{-- The list gets its own band, under the sentence that introduces it, so
         "Our compost comes in two types:" is never left pointing at nothing. --}}
    <section class="pb-12 sm:pb-16">
        <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
            <div class="max-w-3xl" data-reveal>
                <p class="content-eyebrow">{{ __('ui.next_projects.highlights') }}</p>

                @if (filled($brief->listIntro))
                    <p class="mt-4 text-xl font-semibold leading-8 text-balance text-stone-800 sm:text-2xl">
                        {{ $brief->listIntro }}
                    </p>
                @endif
            </div>

            <ul @class([
                'mt-8 grid gap-4 sm:grid-cols-2',
                'lg:grid-cols-3' => count($brief->highlights) >= 3,
            ]) data-reveal-group>
                @foreach ($brief->highlights as $highlight)
                    <li class="content-card p-6" data-reveal>
                        <div class="flex items-start gap-4">
                            <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-lime-300 text-stone-950" aria-hidden="true">
                                <x-site.icon name="check" class="h-5 w-5" />
                            </span>
                            <div class="min-w-0">
                                @if (filled($highlight['label']))
                                    <p class="text-base font-bold text-stone-900">{{ $highlight['label'] }}</p>
                                    <p class="mt-1.5 text-sm leading-7 text-stone-600">{{ $highlight['text'] }}</p>
                                @else
                                    <p class="text-sm leading-7 text-stone-700">{{ $highlight['text'] }}</p>
                                @endif
                            </div>
                        </div>
                    </li>
                @endforeach
            </ul>
        </div>
    </section>
@endif

@if ($brief->body !== '')
    <section class="pb-14 sm:pb-20">
        <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
            <div class="mx-auto max-w-3xl" data-reveal>
                <div class="relative overflow-hidden rounded-[1.75rem] border border-green-700/10 bg-gradient-to-br from-green-50/80 via-white to-lime-50/60 p-7 sm:p-10">
                    <span class="absolute inset-y-0 left-0 w-1.5 bg-gradient-to-b from-lime-400 to-green-600" aria-hidden="true"></span>
                    <div class="rich-text pl-2 sm:pl-4">
                        {!! rich_text($brief->body) !!}
                    </div>
                </div>
            </div>
        </div>
    </section>
@endif

@if ($siblings->isNotEmpty())
    <section class="border-t border-stone-200/80 bg-stone-50/60 py-14 sm:py-20">
        <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
            <div class="flex flex-wrap items-end justify-between gap-4" data-reveal>
                <h2 class="text-2xl font-black tracking-tight text-stone-900 sm:text-3xl">
                    {{ __('ui.next_projects.more_title') }}
                </h2>
                @if ($listing !== null)
                    <a href="{{ locale_path($listing) }}" wire:navigate class="inline-flex items-center gap-2 text-sm font-semibold text-green-700 transition hover:gap-3">
                        {{ __('ui.common.browse_all') }}
                        <x-site.icon name="arrow-right" class="h-4 w-4" />
                    </a>
                @endif
            </div>

            <div class="mt-8 grid gap-6 md:grid-cols-3" data-reveal-group>
                @foreach ($siblings as $sibling)
                    <article class="content-card" data-reveal>
                        <a href="{{ locale_path($sibling->path()) }}" wire:navigate class="group flex h-full flex-col">
                            <div class="relative aspect-[16/10] overflow-hidden bg-gradient-to-br from-lime-50 to-green-50">
                                @if ($sibling->featuredImage !== null)
                                    <img
                                        src="{{ $sibling->featuredImage->url() }}"
                                        alt="{{ $sibling->featuredImage->alt ?: $sibling->localizedTitle() }}"
                                        class="h-full w-full object-contain p-5 transition duration-700 group-hover:scale-105"
                                        loading="lazy"
                                        decoding="async"
                                    >
                                @else
                                    <div class="flex h-full w-full items-center justify-center text-green-700">
                                        <x-site.icon name="leaf" class="h-10 w-10" />
                                    </div>
                                @endif
                            </div>

                            <div class="flex flex-1 flex-col p-6">
                                <h3 class="text-base font-bold leading-7 text-stone-900 transition group-hover:text-green-700">
                                    {{ $sibling->localizedTitle() }}
                                </h3>
                                <span class="mt-auto inline-flex items-center gap-2 pt-4 text-sm font-semibold text-green-700">
                                    {{ __('ui.common.read_more') }}
                                    <x-site.icon name="arrow-right" class="h-4 w-4" />
                                </span>
                            </div>
                        </a>
                    </article>
                @endforeach
            </div>
        </div>
    </section>
@endif

@if ($contact !== null)
    <section class="bg-green-700 px-4 py-16 text-center text-white sm:py-20">
        <div class="mx-auto max-w-3xl" data-reveal>
            <p class="text-xs font-semibold tracking-[0.25em] text-lime-300 uppercase">{{ __('ui.next_projects.cta_eyebrow') }}</p>
            <h2 class="mt-3 text-3xl font-bold text-balance sm:text-4xl">{{ __('ui.next_projects.cta_title') }}</h2>
            <p class="mt-4 leading-8 text-green-100">{{ __('ui.next_projects.cta_text') }}</p>
            <a
                href="{{ locale_path($contact) }}"
                wire:navigate
                class="btn-glow mt-8 inline-flex items-center gap-2 rounded-full bg-white px-7 py-3 text-sm font-semibold text-green-700 transition hover:bg-green-50"
            >
                {{ __('ui.next_projects.cta_button') }}
                <x-site.icon name="arrow-right" class="h-4 w-4" />
            </a>
        </div>
    </section>
@endif
