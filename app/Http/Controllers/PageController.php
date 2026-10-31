<?php

namespace App\Http\Controllers;

use App\Blocks\CollectionHydrator;
use App\Models\Language;
use App\Models\Page;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;

/**
 * Renders a page and the blocks the editor put on it.
 *
 * Every route ends here: the site has no hand-written page templates, so
 * changing the home page or a product page is a content change, not a code
 * change. The only fallback is the very first run, when no page exists yet.
 *
 * Actions read their `{slug}` from the route by name rather than as a method
 * argument. The localized routes prepend a `{locale}` segment, and Laravel
 * fills scalar arguments positionally, so `show($slug)` would receive the
 * language code on `/my/about`.
 */
class PageController extends Controller
{
    public function __construct(private readonly CollectionHydrator $hydrator) {}

    public function home(): View
    {
        // The editor decides which page is the front door; `home` is only the
        // address the importer and the seeders use.
        $slug = (string) setting('site.home_slug', 'home');

        $page = $this->published()->where('slug', $slug !== '' ? $slug : 'home')->first();

        // A brand-new installation has no content: show the setup screen
        // instead of a 404 so the first visit still makes sense.
        return $page === null
            ? view('pages.empty')
            : $this->render($page);
    }

    /** A page addressed by its slug: /about, /faq, /our-story. */
    public function show(): View
    {
        $slug = $this->routeSlug();

        // `/ru` has no slug segment, so it reaches this route. Render the home
        // page in that language instead of looking for a page called "ru".
        if (in_array($slug, Language::activeCodes(), true)) {
            if ($slug === Language::defaultCode()) {
                return redirect()->to('/');
            }

            app()->setLocale($slug);

            return $this->home();
        }

        $page = $this->published()
            ->where('slug', $slug)
            ->whereNotIn('kind', [Page::KIND_PRODUCT, Page::KIND_NEWS])
            ->firstOrFail();

        return $this->render($page);
    }

    /** A product, addressed by its own slug: /products/ruma-gold. */
    public function product(): View
    {
        return $this->render(
            $this->published()->ofKind(Page::KIND_PRODUCT)->where('slug', $this->routeSlug())->firstOrFail(),
        );
    }

    /** A news article: /news/harvest-season-2026. */
    public function post(): View
    {
        return $this->render(
            $this->published()->ofKind(Page::KIND_NEWS)->where('slug', $this->routeSlug())->firstOrFail(),
        );
    }

    private function routeSlug(): string
    {
        return (string) request()->route('slug');
    }

    private function published(): Builder
    {
        return Page::query()
            ->published()
            ->with(['featuredImage', 'translations', 'galleryImages']);
    }

    private function render(Page $page): View
    {
        return view('pages.show', [
            'page' => $page,
            'blocks' => $this->hydrator->hydrate($page->localizedContentBlocks(), $page),
            'seo' => $page->seoMeta(),
        ]);
    }
}
