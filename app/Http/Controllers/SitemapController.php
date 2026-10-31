<?php

namespace App\Http\Controllers;

use App\Models\Language;
use App\Models\Page;
use Illuminate\Http\Response;

/** XML sitemap: every published page, in every active language. */
class SitemapController extends Controller
{
    public function __invoke(): Response
    {
        $pages = Page::query()
            ->published()
            ->ordered()
            ->get();

        return response()
            ->view('sitemap', [
                'pages' => $pages,
                'locales' => Language::activeCodes(),
            ])
            ->header('Content-Type', 'application/xml; charset=UTF-8');
    }
}
