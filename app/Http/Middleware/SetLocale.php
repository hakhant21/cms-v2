<?php

namespace App\Http\Middleware;

use App\Models\Language;
use App\Support\LocaleUrl;
use Closure;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Resolves the language from the URL prefix.
 *
 * The URL is the single source of truth: `/about` is the default language and
 * `/my/about` is Burmese. A prefix that is not an active language is rejected
 * instead of silently rendering the wrong language.
 */
class SetLocale
{
    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $requested = $request->route()?->parameter('locale');

        if (is_string($requested) && $requested !== '') {
            if (! in_array($requested, Language::activeCodes(), true)) {
                abort(404);
            }

            // The default language lives at the root, so `/en/about` is a
            // duplicate URL. Send it to the canonical `/about`.
            if ($requested === Language::defaultCode()) {
                return redirect()->to(
                    LocaleUrl::strip($request->getPathInfo())
                        ?: '/',
                    301,
                );
            }

            app()->setLocale($requested);
        } else {
            app()->setLocale(Language::defaultCode());
        }

        return $next($request);
    }

    /** Send a visitor to the same path in another language. */
    public static function redirectTo(string $locale, string $path): RedirectResponse
    {
        return redirect()->to(LocaleUrl::path($path, $locale));
    }
}
