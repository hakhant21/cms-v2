@php
    use App\Models\Language;
    use App\Support\LocaleUrl;

    $meta = $seo ?? (isset($page) && $page ? $page->seoMeta() : [
        'title' => $siteName,
        'description' => $tagline ?: null,
        'image' => null,
        'canonical' => url()->current(),
        'type' => 'website',
        'site_name' => $siteName,
        'robots' => 'index,follow',
    ]);

    // Every language gets its own canonical URL, which is what search engines
    // need to serve the right version to the right country.
    $alternates = [];
    $basePath = LocaleUrl::strip(request()->path());

    foreach (Language::query()->active()->get() as $language) {
        $alternates[$language->code] = url(LocaleUrl::path($basePath, $language->code));
    }
@endphp
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="csrf-token" content="{{ csrf_token() }}">

<title>{{ $meta['title'] }}</title>
@if (! empty($meta['description']))
    <meta name="description" content="{{ \Illuminate\Support\Str::limit(strip_tags($meta['description']), 160) }}">
@endif
<meta name="robots" content="{{ $meta['robots'] }}">
<link rel="canonical" href="{{ $meta['canonical'] }}">

@foreach ($alternates as $code => $url)
    <link rel="alternate" hreflang="{{ $code }}" href="{{ $url }}">
@endforeach
<link rel="alternate" hreflang="x-default" href="{{ $alternates[\App\Models\Language::defaultCode()] ?? url('/') }}">

<meta property="og:type" content="{{ $meta['type'] }}">
<meta property="og:site_name" content="{{ $meta['site_name'] }}">
<meta property="og:title" content="{{ $meta['title'] }}">
@if (! empty($meta['description']))
    <meta property="og:description" content="{{ \Illuminate\Support\Str::limit(strip_tags($meta['description']), 160) }}">
@endif
<meta property="og:url" content="{{ $meta['canonical'] }}">
@if (! empty($meta['image']))
    <meta property="og:image" content="{{ $meta['image'] }}">
    <meta name="twitter:card" content="summary_large_image">
    <meta name="twitter:image" content="{{ $meta['image'] }}">
@else
    <meta name="twitter:card" content="summary">
@endif
<meta name="twitter:title" content="{{ $meta['title'] }}">

<link rel="icon" type="image/svg+xml" href="/favicon.svg">
<link rel="icon" type="image/png" href="/logo-mark.png">
<link rel="apple-touch-icon" href="/apple-touch-icon.png">

@vite(['resources/css/app.css', 'resources/css/motion.css', 'resources/js/app.js'])

{{-- Livewire on every page, not only the ones with the chat bubble: its
     `wire:navigate` links swap pages without a full browser reload. --}}
@livewireScripts

@if (\App\Support\Chat::enabled())
    {{-- Echo is a separate entry, pulled in only by a site that really offers a
         chat: the broadcast client is most of the site's JavaScript and pages
         without a bubble have no business shipping it. --}}
    @vite('resources/js/echo.js')
@endif

@if ($themeStyle)
    <style>.site-shell { {{ $themeStyle }} }</style>
@endif

<script type="application/ld+json">
    {!! json_encode([
        '@context' => 'https://schema.org',
        '@type' => 'Organization',
        'name' => $siteName,
        'url' => url('/'),
        'email' => setting('company.email'),
        'telephone' => setting('company.phone'),
        'address' => setting('company.address') ? [
            '@type' => 'PostalAddress',
            'streetAddress' => setting('company.address'),
        ] : null,
    ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) !!}
</script>

@if (isset($page) && $page)
    {{-- The page describes itself: type, language and the trail that reached it. --}}
    <script type="application/ld+json">
        {!! json_encode($page->structuredData(), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) !!}
    </script>
@endif
