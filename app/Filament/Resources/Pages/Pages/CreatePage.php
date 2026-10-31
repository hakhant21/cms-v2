<?php

namespace App\Filament\Resources\Pages\Pages;

use App\Filament\Resources\Pages\PageResource;
use App\Support\PageTranslations;
use Filament\Resources\Pages\CreateRecord;

class CreatePage extends CreateRecord
{
    protected static string $resource = PageResource::class;

    /** @var array<string, array<string, mixed>> */
    protected array $translations = [];

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $this->translations = PageTranslations::extract($data);

        return $data;
    }

    protected function afterCreate(): void
    {
        PageTranslations::sync($this->record, $this->translations);
        $this->record->reorderGallery(array_map('intval', (array) ($this->data['gallery'] ?? [])));
    }

    /** Straight into editing, where the language tabs are visible. */
    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('edit', ['record' => $this->record]);
    }
}
