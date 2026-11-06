@props(['title', 'text' => null, 'image' => null, 'alt' => null])

{{-- `text-white` is what tells the site-wide section colour rule to leave the
     banner alone: the backdrop is a dark gradient, so the type must stay light. --}}
<section class="page-banner relative overflow-hidden text-white" data-reveal>
    @if ($image)
        <div
            class="page-banner__media"
            style="background-image: url('{{ $image }}')"
            role="img"
            aria-label="{{ $alt ?: $title }}"
        ></div>
    @else
        <div class="page-banner__gradient" aria-hidden="true"></div>
    @endif
    <div class="page-banner__scrim" aria-hidden="true"></div>

    <nav aria-label="Breadcrumb" class="page-banner__crumbs">
        <a href="{{ locale_path('/') }}" wire:navigate class="page-banner__crumb-link">{{ __('ui.nav.home_page') }}</a>
        <span class="page-banner__crumb-sep" aria-hidden="true">/</span>
        <span class="page-banner__crumb-current" aria-current="page">{{ $title }}</span>
    </nav>

    <div class="page-banner__inner">
        <h1 class="page-banner__title">{{ $title }}</h1>
        @if ($text)
            <p class="page-banner__text">{{ $text }}</p>
        @endif
    </div>
</section>
