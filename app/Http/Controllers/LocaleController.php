<?php

namespace App\Http\Controllers;

use App\Models\Language;
use App\Support\LocaleUrl;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Switches the visitor to another language while staying on the same page.
 *
 * The target path is taken from the URL rather than from a stored "last page",
 * so a shared link always lands where the visitor expects.
 */
class LocaleController extends Controller
{
    public function __invoke(Request $request, string $locale): RedirectResponse
    {
        abort_unless(in_array($locale, Language::activeCodes(), true), 404);

        // The page the visitor is on travels in the query string, because the
        // switch route itself lives outside the language prefix.
        $path = $request->string('path')->toString();
        $path = $path !== '' ? '/'.ltrim($path, '/') : $request->getPathInfo();

        $target = LocaleUrl::path(LocaleUrl::strip($path), $locale);

        return redirect()->to($target);
    }
}
