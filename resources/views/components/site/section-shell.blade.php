@props(['size' => 'md'])

@php
    $padding = match ($size) {
        'sm' => 'py-10 sm:py-12',
        'lg' => 'py-16 sm:py-24',
        'flush' => 'py-6',
        default => 'py-12 sm:py-16',
    };
@endphp

<section {{ $attributes->merge(['class' => $padding]) }}>
    <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
        {{ $slot }}
    </div>
</section>
