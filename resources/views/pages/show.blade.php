@extends('layouts.site')

@php
    $kind = $page->kind;
    $showShare = in_array($kind, [\App\Models\Page::KIND_NEWS, \App\Models\Page::KIND_PRODUCT], true);
    $excerpt = $page->localizedExcerpt();

    // A product page has no banner of its own, so the picture and the name are
    // put in front of its sections. When an editor did add a banner, that
    // banner already carries the heading and the product panel is skipped.
    $opensWithBanner = in_array(
        $blocks[0]['data']['layout'] ?? $blocks[0]['type'] ?? null,
        ['banner', 'hero'],
        true,
    );

    // A project is written as one plain rich-text block. Lifting that block
    // into its own showcase means the featured image, the heading and the
    // highlights share the first screen instead of collapsing into one card.
    $projectIntro = null;

    if ($kind === \App\Models\Page::KIND_PROJECT && ! $opensWithBanner && isset($blocks[0])) {
        $first = $blocks[0];
        $isPlainSection = ($first['type'] ?? null) === 'section'
            && ($first['data']['layout'] ?? null) === 'plain';

        if ($isPlainSection) {
            foreach (($first['data']['fields'] ?? []) as $field) {
                if (($field['type'] ?? null) === 'rich_text' && filled($field['value'] ?? null)) {
                    $projectIntro = $first;

                    break;
                }
            }
        }
    }
@endphp

@section('content')
    @if ($preview ?? false)
        {{-- Editorial preview: never reached by a visitor, so it can say what
             the admin cannot show anywhere else. --}}
        <div class="sticky top-0 z-50 border-b border-amber-300 bg-amber-100/95 backdrop-blur">
            <div class="mx-auto flex max-w-7xl flex-wrap items-center gap-x-4 gap-y-2 px-4 py-2.5 text-xs sm:px-6 lg:px-8">
                <span class="inline-flex items-center gap-1.5 font-bold tracking-[0.16em] text-amber-900 uppercase">
                    <x-site.icon name="alert" class="h-3.5 w-3.5" />
                    Preview
                </span>
                <span class="text-amber-800">
                    {{ $page->localizedTitle() }} — status: {{ $page->status }}
                </span>
                <span class="ml-auto flex items-center gap-1.5">
                    @foreach ($languages as $language)
                        <a
                            href="{{ route('preview', ['page' => $page, 'locale' => $language->code]) }}"
                            @class([
                                'rounded-full px-2.5 py-1 font-semibold transition',
                                'bg-amber-900 text-amber-50' => $language->code === app()->getLocale(),
                                'text-amber-900 hover:bg-amber-200' => $language->code !== app()->getLocale(),
                            ])
                        >{{ $language->native_name ?: $language->name }}</a>
                    @endforeach
                    <a
                        href="{{ url('/admin/pages/'.$page->getKey().'/edit') }}"
                        class="rounded-full bg-amber-900 px-3 py-1 font-semibold text-amber-50 transition hover:bg-amber-800"
                    >Back to editor</a>
                </span>
            </div>
        </div>
    @endif

    @if ($blocks === [])
        {{-- A page with no blocks yet still has to say something useful. --}}
        <section class="py-20 sm:py-28">
            <div class="mx-auto max-w-3xl px-4 text-center sm:px-6 lg:px-8" data-reveal>
                <p class="content-eyebrow justify-center">{{ ucfirst($kind) }}</p>
                <h1 class="mt-4 text-4xl font-black tracking-tight text-stone-900 sm:text-5xl">
                    {{ $page->localizedTitle() }}
                </h1>

                @if ($excerpt)
                    <p class="mt-5 text-lg leading-8 text-stone-600">{{ $excerpt }}</p>
                @endif

                <p class="mt-8 text-sm text-stone-400">
                    This page has no blocks yet. Add some in the admin to fill it.
                </p>
            </div>
        </section>
    @else
        @if ($kind === \App\Models\Page::KIND_PRODUCT && ! $opensWithBanner)
            <x-site.product-media :page="$page" />
        @endif

        @if ($projectIntro !== null)
            <x-site.project-showcase :page="$page" :block="$projectIntro" />
        @endif

        @foreach ($blocks as $index => $block)
            @continue($projectIntro !== null && $index === 0)
            <x-site.block :block="$block" />
        @endforeach
    @endif

    @if ($showShare)
        <div class="mx-auto max-w-7xl px-4 pb-16 sm:px-6 lg:px-8">
            <div class="flex flex-wrap items-center gap-3 border-t border-stone-200 pt-6" data-reveal>
                <span class="text-xs font-semibold tracking-[0.18em] text-stone-500 uppercase">Share</span>
                <button
                    type="button"
                    class="inline-flex items-center gap-2 rounded-full border border-stone-300 px-4 py-2 text-xs font-semibold text-stone-700 transition hover:border-green-700 hover:text-green-700"
                    data-share="{{ url()->current() }}"
                    data-share-copied="Link copied"
                >
                    <x-site.icon name="external" class="h-3.5 w-3.5" />
                    Copy link
                </button>
            </div>
        </div>
    @endif
@endsection
