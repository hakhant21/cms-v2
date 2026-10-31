@props([
    'code' => '404',
    'title',
    'body',
])

@php
    use App\Support\LocaleUrl;

    // The links come from the header navigation the editor maintains, so an
    // error page can never point at a page that was renamed or deleted.
    $links = ($headerNav ?? collect())->take(5);
@endphp

<section class="relative overflow-hidden py-24 sm:py-32">
    <div class="mx-auto max-w-3xl px-4 text-center sm:px-6 lg:px-8" data-reveal>
        <p class="content-eyebrow justify-center">{{ __('ui.errors.code', ['code' => $code]) }}</p>

        <p class="mt-6 text-[6rem] leading-none font-black tracking-tighter text-green-700/15 select-none sm:text-[9rem]" aria-hidden="true">
            {{ $code }}
        </p>

        <h1 class="-mt-8 text-3xl font-black tracking-tight text-stone-900 sm:text-4xl">
            {{ $title }}
        </h1>

        <p class="mx-auto mt-5 max-w-xl text-base leading-7 text-stone-600">
            {{ $body }}
        </p>

        <div class="mt-9 flex flex-wrap items-center justify-center gap-3">
            <a
                href="{{ LocaleUrl::path('/') }}"
                class="btn-glow inline-flex items-center gap-2 rounded-full bg-green-700 px-7 py-3.5 text-sm font-semibold text-white transition hover:bg-green-800"
            >
                <x-site.icon name="arrow-left" class="h-4 w-4" />
                {{ __('ui.errors.go_home') }}
            </a>
        </div>

        @if ($links->isNotEmpty())
            <nav class="mt-14 border-t border-stone-200 pt-8" aria-label="{{ __('ui.footer.quick_links') }}">
                <p class="text-xs font-semibold tracking-[0.18em] text-stone-500 uppercase">
                    {{ __('ui.footer.quick_links') }}
                </p>
                <ul class="mt-4 flex flex-wrap items-center justify-center gap-x-6 gap-y-2">
                    @foreach ($links as $item)
                        <li>
                            <a
                                href="{{ LocaleUrl::path($item->href()) }}"
                                class="inline-flex items-center gap-1.5 text-sm font-semibold text-green-800 transition hover:text-green-600"
                            >
                                {{ $item->localizedLabel() }}
                                <x-site.icon name="arrow-right" class="h-3.5 w-3.5" />
                            </a>
                        </li>
                    @endforeach
                </ul>
            </nav>
        @endif
    </div>
</section>
