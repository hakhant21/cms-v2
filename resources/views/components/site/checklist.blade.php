@props(['items' => [], 'columns' => 2])

@if ($items !== [])
    <ul @class([
        'grid gap-3',
        'sm:grid-cols-2' => $columns > 1,
    ])>
        @foreach ($items as $item)
            <li class="benefits-checklist__item">
                <span class="benefits-checklist__tick" aria-hidden="true">
                    <svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round">
                        <path d="m20 6-11 11-5-5" />
                    </svg>
                </span>
                <span>{{ $item }}</span>
            </li>
        @endforeach
    </ul>
@endif
