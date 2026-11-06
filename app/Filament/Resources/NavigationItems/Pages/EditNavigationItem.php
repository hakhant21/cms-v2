<?php

namespace App\Filament\Resources\NavigationItems\Pages;

use App\Filament\Resources\NavigationItems\NavigationItemResource;
use App\Models\NavigationItemTranslation;
use App\Support\NavigationLabels;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Support\Collection;

class EditNavigationItem extends EditRecord
{
    protected static string $resource = NavigationItemResource::class;

    /**
     * The label rows as stored before this save, keyed by locale. Saving
     * compares against them to tell a label the editor rewrote from one the
     * machine owns. Captured in the `beforeSave` hook: Filament calls it in
     * the same request, just before the repeater writes its rows — a mount()
     * capture would not survive Livewire's per-request rehydration.
     *
     * @var Collection<string, NavigationItemTranslation>|null
     */
    protected ?Collection $savedLabels = null;

    public function beforeSave(): void
    {
        $this->savedLabels = $this->getRecord()->translations()->get()->keyBy('locale');
    }

    protected function afterSave(): void
    {
        NavigationLabels::sync($this->getRecord(), $this->savedLabels ?? collect());
    }

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
