@php
    $image = $section->field('image');
    $src = media_url(is_string($image['value'] ?? null) ? $image['value'] : null);
    $fallbackTitle = ($page ?? null)?->localizedTitle() ?? '';
@endphp

<x-site.page-banner
    :title="$section->value('title') ?: $fallbackTitle"
    :text="$section->value('paragraph')"
    :image="$src"
    :alt="(string) ($image['alt'] ?? '')"
/>
