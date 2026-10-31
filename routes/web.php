<?php

use App\Http\Controllers\LocaleController;
use App\Http\Controllers\PageController;
use App\Http\Controllers\PreviewController;
use App\Http\Controllers\SitemapController;
use App\Http\Middleware\SetLocale;
use Illuminate\Support\Facades\Route;

/*
 * Public site routes.
 *
 * There is one route per URL shape, never one per page: the URL is matched to
 * a row in `pages`, and the blocks stored with that page decide what renders.
 * Adding a page in the admin is therefore enough to publish a new URL.
 *
 * The default language lives at the root (`/about`); every other language is
 * prefixed with its code (`/my/about`). The two groups share the same
 * definition so they can never drift apart.
 */
$pages = function (string $namePrefix = ''): void {
    Route::get('/', [PageController::class, 'home'])->name($namePrefix.'home');

    Route::get('/products/{slug}', [PageController::class, 'product'])
        ->where('slug', '[A-Za-z0-9\-_]+')
        ->name($namePrefix.'product');

    Route::get('/news/{slug}', [PageController::class, 'post'])
        ->where('slug', '[A-Za-z0-9\-_]+')
        ->name($namePrefix.'post');

    // Every other page is addressed by its own slug, including the listing
    // pages editors create for products, news and FAQs.
    Route::get('/{slug}', [PageController::class, 'show'])
        ->where('slug', '[A-Za-z0-9\-_]+')
        ->name($namePrefix.'page');
};

Route::middleware([SetLocale::class])->group(fn () => $pages());

Route::prefix('{locale}')
    ->where(['locale' => '[a-z]{2,3}(?:-[A-Za-z]{2,4})?'])
    ->middleware([SetLocale::class])
    ->group(fn () => $pages('localized.'));

Route::get('/locale/{locale}', LocaleController::class)
    ->where('locale', '[a-z]{2,3}(?:-[A-Za-z]{2,4})?')
    ->name('locale.switch');

Route::get('/sitemap.xml', SitemapController::class)->name('sitemap');

// Editors check a page before publishing it. Anything logged in can look, and
// the page's status is deliberately irrelevant here.
Route::get('/preview/{page}', PreviewController::class)
    ->middleware('auth')
    ->name('preview');
