<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| Filament
|--------------------------------------------------------------------------
|
| Only the keys this application changes live here. Filament merges the rest
| of its defaults in from the package, so publishing the full file would only
| freeze settings we never chose.
|
*/

return [

    /*
    |--------------------------------------------------------------------------
    | Broadcasting
    |--------------------------------------------------------------------------
    |
    | Filament ships its own copy of Laravel Echo (public/js/filament/filament/
    | echo.js) and, when `broadcasting.echo` is filled, builds `window.Echo`
    | from it on every admin page. That is how the live chat inbox hears about
    | new visitor messages without a second copy of the client in the panel's
    | asset pipeline.
    |
    | Leaving REVERB_APP_KEY empty removes the block entirely: the inbox then
    | relies on its `wire:poll` refresh, so replies still arrive — only later.
    | The `/broadcasting/auth` route behind `authEndpoint` is what authorises
    | the private `chat.agents` channel, using the same web session that logs an
    | agent into the panel.
    */

    'broadcasting' => [

        'echo' => filled($key = env('REVERB_APP_KEY')) ? [
            'broadcaster' => 'reverb',
            'key' => $key,
            'wsHost' => env('REVERB_HOST', 'localhost'),
            'wsPort' => (int) env('REVERB_PORT', 8080),
            'wssPort' => (int) env('REVERB_PORT', 443),
            'forceTLS' => env('REVERB_SCHEME', 'https') === 'https',
            'enabledTransports' => ['ws', 'wss'],
            'authEndpoint' => '/broadcasting/auth',
            'disableStats' => true,
        ] : null,

    ],

];
