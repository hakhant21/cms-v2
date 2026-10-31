<?php

namespace App\Http\Middleware;

use App\Models\Language;
use App\Models\Redirect;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Answers an old address with the new one.
 *
 * This runs as global middleware rather than inside the public route group
 * because a missing page is exactly the case where no route matched: route
 * middleware never gets a chance to run when the router is about to throw a
 * 404. Only reads are considered, and the admin panel is skipped entirely.
 */
class RedirectLegacyPaths
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! $request->isMethod('GET') && ! $request->isMethod('HEAD')) {
            return $next($request);
        }

        if ($request->is('admin', 'admin/*', 'livewire/*', 'up')) {
            return $next($request);
        }

        $path = trim($request->path(), '/');

        if ($path === '') {
            return $next($request);
        }

        // Split the language prefix off by hand: the locale share of the URL is
        // the middleware's business here, not the router's.
        $segments = explode('/', $path);
        $locale = Language::defaultCode();

        if (in_array($segments[0], Language::activeCodes(), true)) {
            $locale = (string) array_shift($segments);
        }

        $redirect = Redirect::forPath(implode('/', $segments));

        if ($redirect === null) {
            return $next($request);
        }

        $target = $redirect->target($locale);

        if ($request->getQueryString()) {
            $target .= '?'.$request->getQueryString();
        }

        // An editor can point a row at itself by accident; following it would
        // loop forever, so the page is served as if the row were not there.
        if ($target === $request->getRequestUri()) {
            return $next($request);
        }

        $redirect->increment('hits');

        return redirect()->to($target, $redirect->status_code);
    }
}
