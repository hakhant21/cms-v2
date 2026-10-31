@props([
    'src' => null,
    'name' => '',
    'size' => 'h-11 w-11',
    'ring' => true,
])

@php
    // Initials sit underneath the photo, so a missing or broken portrait still
    // reads as an avatar instead of spilling alt text out of the circle.
    $initials = collect(preg_split('/\s+/', trim((string) $name)) ?: [])
        ->filter()
        ->take(2)
        ->map(fn (string $part): string => mb_strtoupper(mb_substr($part, 0, 1)))
        ->implode('');
@endphp

<span @class([
    'relative inline-flex shrink-0 items-center justify-center overflow-hidden rounded-full bg-green-100 text-sm font-bold text-green-700',
    $size,
    'ring-2 ring-lime-300' => $ring,
])>
    <span aria-hidden="true">{{ $initials !== '' ? $initials : '·' }}</span>

    @if (filled($src))
        <img
            src="{{ $src }}"
            alt="{{ $name }}"
            class="absolute inset-0 h-full w-full object-cover"
            loading="lazy"
            decoding="async"
            onerror="this.remove()"
        >
    @endif
</span>
