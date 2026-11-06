<?php

namespace App\Filament\Resources\NavigationItems\Pages;

use App\Filament\Resources\NavigationItems\NavigationItemResource;
use App\Support\NavigationLabels;
use Filament\Resources\Pages\CreateRecord;

class CreateNavigationItem extends CreateRecord
{
    protected static string $resource = NavigationItemResource::class;

    /**
     * The labels were all just typed, so every one of them is hand-written;
     * the remaining languages are translated from them in the background.
     */
    protected function afterCreate(): void
    {
        NavigationLabels::sync($this->getRecord(), collect());
    }
}
