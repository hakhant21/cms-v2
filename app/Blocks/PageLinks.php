<?php

namespace App\Blocks;

use App\Models\Language;
use App\Models\Page;

/**
 * The pages an editor can point a block button at.
 *
 * This site has no route per page — `/about` is a row in `pages`, and adding a
 * page in the admin publishes a new URL by itself. So the list of destinations
 * is read from the pages rather than from a hard-coded set of route names, and
 * it is built with Page::path(), the same rule the router, the breadcrumb and
 * the sitemap use. A page the editor publishes is linkable immediately; one
 * that is unpublished or deleted stops being offered.
 *
 * Addresses are stored without a language prefix (`/about`), because every
 * language serves the same page under its own prefix.
 */
final class PageLinks
{
    /**
     * The kinds that have a page of their own to link to. FAQ entries,
     * testimonials and project rows are shown inside their listing page, so
     * offering them as destinations would only add noise to the picker.
     */
    private const NAVIGABLE = [Page::KIND_PAGE, Page::KIND_PRODUCT, Page::KIND_NEWS];

    /**
     * Path => label, ready for a select field's options.
     *
     * @return array<string, string>
     */
    public static function options(?string $current = null): array
    {
        $home = '/'.ltrim((string) setting('site.home_slug', 'home'), '/');

        $options = ['/' => 'Home'];

        $pages = Page::query()->published()->whereIn('kind', self::NAVIGABLE)->ordered()->with('translations')->get();

        // Plain pages first, then products, then posts: the picker is a search
        // box over this order, and the answer an editor is most likely looking
        // for is a page.
        foreach (self::NAVIGABLE as $kind) {
            foreach ($pages->where('kind', $kind) as $page) {
                $path = $page->path();

                // The front door is already listed as `/`; its own slug would
                // be a second address for the same page.
                if ($path === $home) {
                    continue;
                }

                $options[$path] = self::label($page);
            }
        }

        // Whatever the field already holds stays on the list. Filament checks a
        // select against its own options, so a link whose page has since been
        // unpublished, renamed or deleted would otherwise be reported as
        // "the selected destination is invalid" and stop the editor from saving
        // the page at all — the fix belongs to the link, not to the whole form.
        if (filled($current) && ! array_key_exists($current, $options)) {
            $options[$current] = $current.' (no longer listed)';
        }

        return $options;
    }

    /**
     * The label an editor sees. Titles are read in the site's default language
     * so the list says the same thing whoever is logged in and whichever
     * language tab is open — the address being stored is language-neutral.
     */
    private static function label(Page $page): string
    {
        $title = $page->localizedTitle(Language::defaultCode());

        return match ($page->kind) {
            Page::KIND_PRODUCT => $title.' — product',
            Page::KIND_NEWS => $title.' — post',
            default => $title,
        };
    }
}
