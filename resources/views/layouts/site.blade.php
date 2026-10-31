<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" dir="{{ $direction ?? 'ltr' }}">
<head>
    @include('site.partials.head')
</head>
<body @class([
    'site-shell flex min-h-screen flex-col bg-stone-50 text-stone-900 antialiased',
    'site-animation-'.$animation,
])>
    <a
        href="#main-content"
        class="sr-only focus:not-sr-only focus:absolute focus:top-3 focus:left-3 focus:z-[100] focus:rounded-lg focus:bg-white focus:px-4 focus:py-2 focus:text-sm focus:font-semibold focus:text-stone-900"
    >
        {{ __('ui.common.skip_to_content') }}
    </a>

    <div class="site-ambient" aria-hidden="true">
        <span class="site-ambient-orb site-ambient-orb--lime"></span>
        <span class="site-ambient-orb site-ambient-orb--mint"></span>
        <span class="site-ambient-grid"></span>
    </div>

    @include('site.partials.header')

    <main id="main-content" class="flex-1" role="main">
        <div class="site-route-view">
            @yield('content')
        </div>
    </main>

    @include('site.partials.footer')

    {{-- The bubble belongs to the shell rather than to a page block: a visitor
         who wanders off to read a product page and comes back finds the same
         conversation, because the token in their session names it. --}}
    @if (\App\Support\Chat::enabled())
        <livewire:chat-widget />
    @endif

    {{-- One lightbox for the whole site: every image on a page shares it, so a
         gallery of twelve lab reports costs one dialog in the DOM. --}}
    @include('site.partials.lightbox')

    @stack('scripts')
</body>
</html>
