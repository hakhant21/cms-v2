<?php echo '<?xml version="1.0" encoding="UTF-8"?>'."\n"; ?>
<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9" xmlns:xhtml="http://www.w3.org/1999/xhtml">
@foreach ($pages as $page)
@foreach ($locales as $locale)
    <url>
        <loc>{{ url(\App\Support\LocaleUrl::path($page->path(), $locale)) }}</loc>
        @if ($page->published_at)
        <lastmod>{{ $page->published_at->toAtomString() }}</lastmod>
        @endif
        @foreach ($locales as $alternate)
        <xhtml:link rel="alternate" hreflang="{{ $alternate }}" href="{{ url(\App\Support\LocaleUrl::path($page->path(), $alternate)) }}" />
        @endforeach
    </url>
@endforeach
@endforeach
</urlset>
