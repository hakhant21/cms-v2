<?php

namespace App\Http\Controllers;

use App\Blocks\CollectionHydrator;
use App\Models\Language;
use App\Models\Page;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

/**
 * Renders a page exactly as the public site would, whatever its status.
 *
 * Editors need to see a draft before it goes live, and a page under review is
 * usually still incomplete, so the normal routes would 404 it. The route is
 * behind the auth middleware and the same block renderer is used, which means
 * the preview can never drift from the published result.
 */
class PreviewController extends Controller
{
    public function __construct(private readonly CollectionHydrator $hydrator) {}

    public function __invoke(Request $request, Page $page): View
    {
        $locale = $request->string('locale')->toString();
        $locale = in_array($locale, Language::activeCodes(), true)
            ? $locale
            : Language::defaultCode();

        app()->setLocale($locale);

        return view('pages.show', [
            'page' => $page,
            'blocks' => $this->hydrator->hydrate($page->localizedContentBlocks($locale), $page),
            'seo' => $page->seoMeta($locale),
            'preview' => true,
        ]);
    }
}
