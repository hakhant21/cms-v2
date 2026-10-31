<?php

use App\Http\Middleware\RedirectLegacyPaths;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        channels: __DIR__.'/../routes/channels.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // The admin panel is the only place anyone signs in, so an editor-only
        // URL such as a page preview sends guests there instead of failing.
        $middleware->redirectGuestsTo('/admin/login');

        // Global, not per route: an old address is one no route answers, and
        // route middleware only runs once a route has already matched.
        $middleware->append(RedirectLegacyPaths::class);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
    })->create();
