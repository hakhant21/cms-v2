@php
    use App\Models\Language;
    use App\Support\LocaleUrl;

    $phone = setting('company.phone');
    $phoneSecondary = setting('company.phone_secondary');
    $email = setting('company.email');

    $isActive = function (string $href) use ($currentPath): bool {
        $target = LocaleUrl::path($href);

        return $target === '/' ? $currentPath === '/' : str_starts_with($currentPath, $target);
    };
@endphp

<header class="site-header sticky top-0 z-50 text-white" data-site-header>
    <div class="border-b border-white/10 bg-white/5 py-2 text-xs text-stone-200">
        <div class="mx-auto grid max-w-7xl grid-cols-1 gap-3 px-4 text-center sm:px-6 md:grid-cols-3 md:text-left lg:px-8">
            @if ($email)
                <a href="mailto:{{ $email }}" class="transition hover:text-lime-300 md:justify-self-start">
                    {{ $email }}
                </a>
            @else
                <span></span>
            @endif

            @if ($phone || $phoneSecondary)
                <span class="site-header-phone hidden text-stone-400 sm:inline md:justify-self-center">
                    {{ trim($phone.' / '.$phoneSecondary, ' /') }}
                </span>
            @endif

            @if ($languages->count() > 1)
                <div class="md:justify-self-end" data-dropdown>
                    <button
                        type="button"
                        class="inline-flex items-center gap-1.5 rounded-full border border-white/20 px-3 py-1 font-semibold tracking-wide text-white/85 uppercase transition hover:border-lime-300/60 hover:text-lime-300"
                        data-dropdown-toggle
                        aria-expanded="false"
                    >
                        <x-site.icon name="globe" class="h-3.5 w-3.5" />
                        {{ $currentLanguage?->code ?? app()->getLocale() }}
                        <x-site.icon name="chevron-down" class="h-3 w-3" />
                    </button>
                    <div class="absolute right-4 z-50 mt-2 hidden min-w-40 overflow-hidden rounded-xl border border-white/10 bg-stone-950/95 text-left shadow-2xl backdrop-blur" data-dropdown-menu>
                        @foreach ($languages as $language)
                            <a
                                href="{{ route('locale.switch', ['locale' => $language->code, 'path' => $currentPath]) }}"
                                @class([
                                    'flex items-center justify-between gap-3 px-4 py-2 text-sm transition hover:bg-white/10',
                                    'text-lime-300' => $language->code === app()->getLocale(),
                                    'text-stone-200' => $language->code !== app()->getLocale(),
                                ])
                            >
                                <span>{{ $language->native_name ?: $language->name }}</span>
                                <span class="text-[0.65rem] tracking-widest text-stone-400 uppercase">{{ $language->code }}</span>
                            </a>
                        @endforeach
                    </div>
                </div>
            @endif
        </div>
    </div>

    <div class="mx-auto flex min-h-20 max-w-7xl items-center justify-between gap-4 px-4 py-4 sm:px-6 lg:px-8">
        <a href="{{ locale_path('/') }}" wire:navigate class="flex items-center gap-2">
            <span class="flex h-12 w-12 items-center justify-center rounded-2xl bg-lime-300/95 shadow-lg shadow-lime-500/10">
                <x-site.icon name="leaf" class="h-7 w-7 text-stone-950" />
            </span>
            <span class="text-xl font-black tracking-wide text-white">{{ $siteName }}</span>
        </a>

        <nav class="hidden items-center gap-1 xl:flex" aria-label="{{ __('ui.nav.home_page') }}">
            @foreach ($headerNav as $item)
                @php $href = LocaleUrl::path($item->href()); @endphp
                @if ($item->children->isEmpty())
                    <a
                        href="{{ $href }}"
                        @if (! $item->opens_new_tab) wire:navigate @endif
                        @class([
                            'rounded-lg px-3 py-2 text-sm font-semibold transition',
                            'text-lime-300' => $isActive($item->href()),
                        ])
                        @if ($item->opens_new_tab) target="_blank" rel="noopener" @endif
                    >
                        {{ $item->localizedLabel() }}
                    </a>
                @else
                    <div class="relative" data-dropdown>
                        <button
                            type="button"
                            class="inline-flex items-center gap-1 rounded-lg px-3 py-2 text-sm font-semibold transition"
                            data-dropdown-toggle
                            aria-expanded="false"
                        >
                            {{ $item->localizedLabel() }}
                            <x-site.icon name="chevron-down" class="h-3.5 w-3.5" />
                        </button>
                        <div class="absolute left-0 z-50 mt-1 hidden min-w-52 overflow-hidden rounded-xl border border-white/10 bg-stone-950/95 py-1 shadow-2xl backdrop-blur" data-dropdown-menu>
                            @foreach ($item->children as $child)
                                <a href="{{ LocaleUrl::path($child->href()) }}" wire:navigate class="block px-4 py-2 text-sm text-stone-200 transition hover:bg-white/10 hover:text-lime-300">
                                    {{ $child->localizedLabel() }}
                                </a>
                            @endforeach
                        </div>
                    </div>
                @endif
            @endforeach
        </nav>

        <div class="flex items-center gap-2">
            @if ($phone)
                <a
                    href="tel:{{ preg_replace('/[^0-9+]/', '', $phone) }}"
                    class="hidden items-center gap-2 rounded-full bg-lime-300 px-4 py-2 text-sm font-bold text-stone-950 transition hover:bg-lime-200 md:inline-flex"
                >
                    <x-site.icon name="phone" class="h-4 w-4" />
                    {{ $phone }}
                </a>
            @endif
            <button
                type="button"
                class="inline-flex items-center justify-center rounded-md p-2 text-white/80 transition hover:bg-white/10 hover:text-lime-300 xl:hidden"
                data-mobile-toggle
                data-label-open="{{ __('ui.nav.open_menu') }}"
                data-label-close="{{ __('ui.nav.close_menu') }}"
                aria-controls="site-mobile-nav"
                aria-expanded="false"
                aria-label="{{ __('ui.nav.open_menu') }}"
            >
                <x-site.icon name="menu" class="h-6 w-6" data-mobile-icon-open />
                <x-site.icon name="close" class="hidden h-6 w-6" data-mobile-icon-close />
            </button>
        </div>
    </div>

    <div id="site-mobile-nav" class="hidden border-t border-white/10 bg-stone-950/95 backdrop-blur xl:hidden" data-mobile-nav>
        <nav class="mx-auto max-w-7xl px-4 py-4 sm:px-6 lg:px-8" aria-label="{{ $siteName }}">
            @foreach ($headerNav as $item)
                @if ($item->children->isEmpty())
                    <a href="{{ LocaleUrl::path($item->href()) }}" wire:navigate class="block rounded-lg px-3 py-2.5 text-sm font-semibold text-white/90 transition hover:bg-white/10 hover:text-lime-300">
                        {{ $item->localizedLabel() }}
                    </a>
                @else
                    {{-- A group, not a destination: the heading names it and the
                         children are the links, so nothing jumps to `#`. --}}
                    <p class="px-3 pt-3 pb-1 text-xs font-bold tracking-wider text-stone-400 uppercase">
                        {{ $item->localizedLabel() }}
                    </p>

                    @foreach ($item->children as $child)
                        <a href="{{ LocaleUrl::path($child->href()) }}" wire:navigate class="block rounded-lg px-3 py-2 pl-6 text-sm font-medium text-stone-300 transition hover:bg-white/10 hover:text-lime-300">
                            {{ $child->localizedLabel() }}
                        </a>
                    @endforeach
                @endif
            @endforeach
        </nav>
    </div>
</header>
