<?php

namespace App\Support;

use App\Models\Language;
use App\Models\Page;
use App\Models\PageTranslation;

/**
 * Bridges the per-locale tabs in the admin form and the `page_translations`
 * rows. The form classes only call state/extract/sync, so nothing else in the
 * application has to know how a translation is stored.
 */
final class PageTranslations
{
    /**
     * Form state for a page: one entry per active locale, filled from the
     * existing row when there is one. Missing locales start empty so an editor
     * can simply start typing in the tab they need.
     *
     * @return array<string, array<string, mixed>>
     */
    public static function state(Page $page): array
    {
        $page->loadMissing('translations');

        $state = [];

        foreach (Language::activeCodes() as $code) {
            $translation = $page->translations->firstWhere('locale', $code);

            $state[$code] = [
                'title' => $translation?->title,
                'excerpt' => $translation?->excerpt,
                'content_blocks' => $translation?->blocks() ?? [],
                'seo' => is_array($translation?->seo) ? $translation->seo : [],
            ];
        }

        return $state;
    }

    /**
     * Pull the `translations` key out of the submitted form data so it never
     * reaches the model's mass assignment.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, array<string, mixed>>
     */
    public static function extract(array &$data): array
    {
        $translations = $data['translations'] ?? [];
        unset($data['translations']);

        return is_array($translations) ? $translations : [];
    }

    /**
     * Persist the submitted locales. A locale whose fields are all empty is
     * deleted, which is how an editor intentionally removes a translation.
     *
     * The share image and the search-engine setting are not language: a locale
     * that has not chosen its own inherits the default language's, so the
     * image is uploaded once for the whole page.
     *
     * @param  array<string, array<string, mixed>>  $state
     */
    public static function sync(Page $page, array $state): void
    {
        $defaultSeo = self::seo($state[Language::defaultCode()]['seo'] ?? null);

        foreach (Language::activeCodes() as $code) {
            $values = $state[$code] ?? [];

            $title = trim((string) ($values['title'] ?? ''));
            $excerpt = trim((string) ($values['excerpt'] ?? ''));
            $blocks = self::blocks($values['content_blocks'] ?? null);
            $seo = self::seo($values['seo'] ?? null);

            foreach (['og_image', 'robots'] as $key) {
                if (! isset($seo[$key]) && isset($defaultSeo[$key])) {
                    $seo[$key] = $defaultSeo[$key];
                }
            }

            if ($title === '' && $excerpt === '' && $blocks === [] && $seo === []) {
                $page->translations()->where('locale', $code)->delete();

                continue;
            }

            PageTranslation::query()->updateOrCreate(
                ['page_id' => $page->getKey(), 'locale' => $code],
                [
                    'title' => $title === '' ? null : $title,
                    'excerpt' => $excerpt === '' ? null : $excerpt,
                    'content_blocks' => $blocks === [] ? null : $blocks,
                    'seo' => $seo === [] ? null : $seo,
                ],
            );
        }

        $page->unsetRelation('translations');
    }

    /**
     * Section arrays with anything malformed removed, re-indexed so the
     * renderer always receives a clean list.
     *
     * @return array<int, array<string, mixed>>
     */
    public static function blocks(mixed $blocks): array
    {
        if (! is_array($blocks)) {
            return [];
        }

        return array_values(array_filter($blocks, 'is_array'));
    }

    /**
     * @return array<string, string>
     */
    private static function seo(mixed $seo): array
    {
        if (! is_array($seo)) {
            return [];
        }

        $seo = array_map(
            fn (mixed $value): string => is_string($value) ? trim($value) : '',
            $seo,
        );

        return array_filter($seo, fn (string $value): bool => $value !== '');
    }
}
