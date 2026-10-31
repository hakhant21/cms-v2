<?php

namespace App\Filament\Pages;

use App\Filament\Widgets\LatestMessages;
use App\Filament\Widgets\SiteOverview;
use App\Filament\Widgets\SoonestPages;
use Filament\Pages\Dashboard as BaseDashboard;

/**
 * The landing screen. It answers "what needs doing" rather than showing
 * default framework widgets.
 */
class Dashboard extends BaseDashboard
{
    protected static ?string $navigationLabel = 'Dashboard';

    protected static ?int $navigationSort = 0;

    public function getWidgets(): array
    {
        return [
            SiteOverview::class,
            LatestMessages::class,
            SoonestPages::class,
        ];
    }

    public function getColumns(): int|array
    {
        return 2;
    }
}
