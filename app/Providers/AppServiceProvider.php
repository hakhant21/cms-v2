<?php

namespace App\Providers;

use App\Blocks\BlockRegistry;
use App\Contracts\Translator;
use App\Support\SiteComposer;
use App\Support\Translation\GeminiTranslator;
use Filament\Support\Facades\FilamentView;
use Filament\Tables\View\TablesRenderHook;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;
use Livewire\Livewire;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // One registry per request: it validates block keys once and every
        // renderer, admin form and migration command reads from the same list.
        $this->app->singleton(BlockRegistry::class);

        // The editor asks for a translator, not for Gemini: swapping provider
        // or using a fake in tests is a one-line binding change.
        $this->app->bind(Translator::class, GeminiTranslator::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // The layout renders the chrome, but a page template sometimes needs
        // the same data (the setup screen greets the visitor by site name), so
        // the composer covers both.
        View::composer(
            ['layouts.site', 'pages.*'],
            SiteComposer::class,
        );

        // Every admin table shows its choice filters as plain selects beside
        // the search box instead of behind a "Filters" button. The hook is
        // global because it has to render inside the table's own toolbar, and
        // `SEARCH_AFTER` is the slot immediately following the search field —
        // reading order puts the search first, then the narrowing controls. It
        // prints nothing for a table without such filters.
        FilamentView::registerRenderHook(
            TablesRenderHook::TOOLBAR_SEARCH_AFTER,
            function (): string {
                $component = Livewire::current();

                if ($component === null || ! method_exists($component, 'getTable')) {
                    return '';
                }

                return view('filament.tables.inline-filters', [
                    'table' => $component->getTable(),
                    'state' => $component->tableFilters ?? null,
                ])->render();
            },
        );
    }
}
