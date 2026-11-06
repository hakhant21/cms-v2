@props(['block', 'page' => null])

@php
    $type = is_array($block) ? ($block['type'] ?? null) : null;
    $data = is_array($block) ? ($block['data'] ?? []) : [];
    $view = $type ? 'blocks.'.$type : null;
    $known = $type !== null && \Illuminate\Support\Facades\View::exists($view);
@endphp

@if ($known)
    @include($view, ['data' => is_array($data) ? $data : [], 'block' => $block, 'page' => $page])
@endif
