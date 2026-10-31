@extends('layouts.site')

@section('content')
    <section class="py-24 sm:py-32">
        <div class="mx-auto max-w-3xl px-4 text-center sm:px-6 lg:px-8" data-reveal>
            <span class="inline-flex h-16 w-16 items-center justify-center rounded-3xl bg-lime-300 text-stone-950">
                <x-site.icon name="leaf" class="h-8 w-8" />
            </span>

            <h1 class="mt-6 text-4xl font-black tracking-tight text-stone-900 sm:text-5xl">
                {{ $siteName }} is ready to be written
            </h1>

            <p class="mt-5 text-lg leading-8 text-stone-600">
                The site is installed but has no home page yet. Sign in to the admin panel,
                create a page with the slug <code class="rounded bg-stone-100 px-1.5 py-0.5 text-sm">home</code>
                and publish it.
            </p>

            <div class="mt-8 flex flex-wrap justify-center gap-3">
                <a
                    href="{{ url('/admin') }}"
                    class="btn-glow inline-flex items-center gap-2 rounded-full bg-green-700 px-8 py-3.5 text-sm font-semibold text-white"
                >
                    Open the admin
                    <x-site.icon name="arrow-right" class="h-4 w-4" />
                </a>
            </div>

            <dl class="mt-14 grid gap-4 text-left sm:grid-cols-3">
                @foreach ([
                    ['Create a page', 'Pick a layout, then add the fields the page should show.'],
                    ['Add blocks', 'Sections, product lists, FAQs and contact forms are all blocks.'],
                    ['Translate', 'Write the same page in every language from one screen.'],
                ] as $index => [$stepTitle, $stepText])
                    <div class="content-card p-5" data-reveal>
                        <dt class="content-card__index">{{ str_pad((string) ($index + 1), 2, '0', STR_PAD_LEFT) }}</dt>
                        <p class="mt-2 font-bold text-stone-900">{{ $stepTitle }}</p>
                        <p class="mt-1 text-sm leading-6 text-stone-600">{{ $stepText }}</p>
                    </div>
                @endforeach
            </dl>
        </div>
    </section>
@endsection
