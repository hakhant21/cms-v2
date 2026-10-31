<?php

namespace App\Support;

use App\Models\Page;

/**
 * Copies a page together with everything a reader sees on it.
 *
 * Editors start most new pages by copying a finished one, so the copy carries
 * the translations, the blocks and the gallery; it does not carry the status,
 * which always starts as a draft so nothing appears on the site by accident.
 */
final class PageDuplicator
{
    public static function duplicate(Page $page): Page
    {
        $copy = Page::query()->create([
            'kind' => $page->kind,
            'slug' => self::availableSlug($page),
            'status' => Page::STATUS_DRAFT,
            'featured_image_id' => $page->featured_image_id,
            'animation' => $page->animation,
            'sort_order' => $page->sort_order,
        ]);

        foreach ($page->translations()->get() as $translation) {
            $copy->translations()->create([
                'locale' => $translation->locale,
                'title' => $translation->title.' (copy)',
                'excerpt' => $translation->excerpt,
                'content_blocks' => $translation->content_blocks,
                'seo' => $translation->seo,
            ]);
        }

        foreach ($page->galleryImages()->get() as $image) {
            $copy->galleryImages()->attach($image->getKey(), [
                'sort_order' => $image->pivot->sort_order,
            ]);
        }

        return $copy;
    }

    /** Slugs are unique per kind, so the copy steps aside instead of failing. */
    private static function availableSlug(Page $page): string
    {
        $base = $page->slug.'-copy';
        $candidate = $base;
        $suffix = 2;

        while (Page::withTrashed()->where('kind', $page->kind)->where('slug', $candidate)->exists()) {
            $candidate = $base.'-'.$suffix++;
        }

        return $candidate;
    }
}
