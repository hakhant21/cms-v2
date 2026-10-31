<?php

/*
 * Machine translation, used by the "Translate" action in the page editor.
 *
 * Nothing here runs unless a key is present: without one the action says so
 * and the editor keeps typing. The key is read from the environment because
 * it belongs to whoever operates the site, not to this repository.
 */
return [
    'provider' => env('AI_TRANSLATION_PROVIDER', 'gemini'),

    'gemini' => [
        'key' => env('GEMINI_API_KEY'),
        'model' => env('GEMINI_MODEL', 'gemini-2.5-flash'),
        'endpoint' => env('GEMINI_ENDPOINT', 'https://generativelanguage.googleapis.com'),
    ],

    // One request carries this many strings. Kept modest so a long page still
    // finishes well inside the timeout, and so a single odd value cannot spoil
    // a whole page's translation.
    'batch' => (int) env('AI_TRANSLATION_BATCH', 40),

    'timeout' => (int) env('AI_TRANSLATION_TIMEOUT', 60),
];
