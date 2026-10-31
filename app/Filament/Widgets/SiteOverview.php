<?php

namespace App\Filament\Widgets;

use App\Models\ContactMessage;
use App\Models\Image;
use App\Models\Language;
use App\Models\Page;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

/** A one-glance picture of the site's health for whoever signs in. */
class SiteOverview extends StatsOverviewWidget
{
    protected static ?int $sort = 1;

    protected int|string|array $columnSpan = 'full';

    protected function getStats(): array
    {
        $published = Page::query()->published()->count();
        $drafts = Page::query()->where('status', Page::STATUS_DRAFT)->count();
        $unread = ContactMessage::query()->unread()->count();
        $newThisWeek = ContactMessage::query()->where('created_at', '>=', now()->subWeek())->count();

        return [
            Stat::make('Published pages', (string) $published)
                ->description(Language::activeCodes() === []
                    ? 'No languages yet'
                    : count(Language::activeCodes()).' language(s) live')
                ->descriptionIcon('heroicon-m-globe-alt')
                ->color('success'),
            Stat::make('Drafts', (string) $drafts)
                ->description($drafts > 0 ? 'Waiting to be finished' : 'Nothing pending')
                ->descriptionIcon('heroicon-m-pencil-square')
                ->color($drafts > 0 ? 'warning' : 'gray'),
            Stat::make('Unread messages', (string) $unread)
                ->description($newThisWeek.' received this week')
                ->descriptionIcon('heroicon-m-inbox')
                ->color($unread > 0 ? 'danger' : 'gray'),
            Stat::make('Media library', (string) Image::query()->count())
                ->description('Images available to every page')
                ->descriptionIcon('heroicon-m-photo')
                ->color('info'),
        ];
    }
}
