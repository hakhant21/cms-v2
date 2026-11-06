@php
    use App\Support\LocaleUrl;

    // The footer owns a menu of its own. The header is only a fallback for a
    // site whose footer has not been filled in yet.
    $footerLinks = $footerNav->isNotEmpty() ? $footerNav : $headerNav;
@endphp

<footer class="site-footer border-t border-white/10 bg-stone-950 text-white">
    <div class="mx-auto max-w-7xl px-4 py-12 sm:px-6 lg:px-8">
        <div class="grid grid-cols-1 gap-10 md:grid-cols-3">
            <div class="space-y-4">
                <div class="flex items-center gap-2">
                    <span class="flex h-10 w-10 items-center justify-center rounded-2xl bg-lime-300">
                        <x-site.icon name="leaf" class="h-6 w-6 text-stone-950" />
                    </span>
                    <span class="text-lg font-black text-white">{{ $siteName }}</span>
                </div>
                <p class="max-w-xs text-sm leading-relaxed text-stone-300">
                    {{ $tagline ?: __('ui.footer.description') }}
                </p>

                @if (filled(setting('site.footer_note')))
                    <p class="max-w-xs text-xs leading-relaxed text-stone-500">
                        {{ setting('site.footer_note') }}
                    </p>
                @endif

                @if ($socialLinks !== [])
                    <div class="pt-1">
                        <p class="text-xs font-semibold tracking-[0.2em] text-stone-400 uppercase">
                            {{ __('ui.footer.follow_us') }}
                        </p>

                        {{-- Only the networks an editor filled in appear, so the
                             row never shows a dead icon. --}}
                        <div class="mt-2.5 flex items-center gap-2">
                            @foreach ($socialLinks as $network => $url)
                                <a
                                    href="{{ $url }}"
                                    target="_blank"
                                    rel="noopener noreferrer"
                                    class="inline-flex h-9 w-9 items-center justify-center rounded-full border border-white/15 text-stone-300 transition hover:-translate-y-0.5 hover:border-lime-300 hover:text-lime-300"
                                    aria-label="{{ ucfirst($network) }}"
                                >
                                    <x-site.icon :name="$network" class="h-4 w-4" />
                                </a>
                            @endforeach
                        </div>
                    </div>
                @endif
            </div>

            <div>
                <h2 class="text-sm font-semibold tracking-[0.2em] text-lime-300 uppercase">
                    {{ __('ui.footer.quick_links') }}
                </h2>

                <nav aria-label="{{ __('ui.footer.quick_links') }}" class="mt-4">
                    <ul class="space-y-2">
                        @foreach ($footerLinks as $item)
                            <li>
                                @if ($item->children->isEmpty())
                                    <a href="{{ LocaleUrl::path($item->href()) }}" wire:navigate class="text-sm text-stone-300 transition-colors hover:text-lime-300">
                                        {{ $item->localizedLabel() }}
                                    </a>
                                @else
                                    {{-- A group is a heading, not a destination: the old
                                         `#` link jumped to the top of the page. --}}
                                    <p class="text-xs font-bold tracking-[0.18em] text-stone-400 uppercase">
                                        {{ $item->localizedLabel() }}
                                    </p>
                                    <ul class="mt-2 space-y-1.5 border-l border-white/10 pl-3">
                                        @foreach ($item->children as $child)
                                            <li>
                                                <a href="{{ LocaleUrl::path($child->href()) }}" wire:navigate class="text-sm text-stone-400 transition-colors hover:text-lime-300">
                                                    {{ $child->localizedLabel() }}
                                                </a>
                                            </li>
                                        @endforeach
                                    </ul>
                                @endif
                            </li>
                        @endforeach
                    </ul>
                </nav>
            </div>

            <div>
                <h2 class="text-sm font-semibold tracking-[0.2em] text-lime-300 uppercase">
                    {{ __('ui.nav.contact') }}
                </h2>
                <ul class="mt-4 space-y-3 text-sm text-stone-300">
                    @if (setting('company.address'))
                        <li class="flex items-start gap-2">
                            <x-site.icon name="map-pin" class="mt-0.5 h-4 w-4 shrink-0 text-lime-300" />
                            <span>{{ setting('company.address') }}</span>
                        </li>
                    @endif
                    @if (setting('company.phone'))
                        <li class="flex items-start gap-2">
                            <x-site.icon name="phone" class="mt-0.5 h-4 w-4 shrink-0 text-lime-300" />
                            <span>
                                <a href="tel:{{ preg_replace('/[^0-9+]/', '', (string) setting('company.phone')) }}" class="hover:text-lime-300">
                                    {{ setting('company.phone') }}
                                </a>
                                @if (setting('company.phone_secondary'))
                                    <br>
                                    <a href="tel:{{ preg_replace('/[^0-9+]/', '', (string) setting('company.phone_secondary')) }}" class="hover:text-lime-300">
                                        {{ setting('company.phone_secondary') }}
                                    </a>
                                @endif
                            </span>
                        </li>
                    @endif
                    @if (setting('company.email'))
                        <li class="flex items-start gap-2">
                            <x-site.icon name="mail" class="mt-0.5 h-4 w-4 shrink-0 text-lime-300" />
                            <a href="mailto:{{ setting('company.email') }}" class="hover:text-lime-300">{{ setting('company.email') }}</a>
                        </li>
                    @endif
                    @if (setting('company.office_hours'))
                        <li class="flex items-start gap-2">
                            <x-site.icon name="clock" class="mt-0.5 h-4 w-4 shrink-0 text-lime-300" />
                            <span>{{ setting('company.office_hours') }}</span>
                        </li>
                    @endif
                </ul>
            </div>
        </div>

        <div class="mt-10 flex flex-col items-center justify-between gap-3 border-t border-white/10 pt-6 text-center sm:flex-row sm:text-left">
            <p class="text-sm text-stone-400">
                &copy; {{ now()->year }} {{ $siteName }}. {{ __('ui.footer.rights_reserved') }}
            </p>
            @auth
                <a href="{{ url('/admin') }}" class="text-xs tracking-wide text-stone-500 uppercase transition hover:text-lime-300">
                    {{ __('ui.footer.contact_team') }}
                </a>
            @endauth
        </div>
    </div>
</footer>
