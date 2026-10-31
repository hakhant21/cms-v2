<?php

namespace App\Filament\Resources\Pages\Pages;

use App\Filament\Resources\Pages\PageResource;
use App\Filament\Resources\Pages\Schemas\PageForm;
use App\Jobs\TranslatePageJob;
use App\Models\Language;
use App\Models\Page;
use App\Support\PageTranslations;
use App\Support\Translation\PageTranslator;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Actions\ForceDeleteAction;
use Filament\Actions\RestoreAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;

class EditPage extends EditRecord
{
    protected static string $resource = PageResource::class;

    /** @var array<string, array<string, mixed>> */
    protected array $translations = [];

    protected function getHeaderActions(): array
    {
        return [
            Action::make('view')
                ->label('Preview')
                ->icon('heroicon-m-arrow-top-right-on-square')
                ->url(fn (): string => $this->record->status === Page::STATUS_PUBLISHED
                    ? url($this->record->path())
                    : route('preview', ['page' => $this->record->getKey()]))
                ->openUrlInNewTab(),
            $this->translateAction(),
            DeleteAction::make(),
            RestoreAction::make(),
            ForceDeleteAction::make(),
        ];
    }

    /**
     * Machine translation, run on the queue rather than in the request.
     *
     * A Gemini run can take minutes, so the editor is freed at once: a job
     * reads the saved source language, writes the chosen targets straight into
     * the page and notifies whoever asked. The translation is live content when
     * it lands, so the notification points back at the page to read over it.
     */
    private function translateAction(): Action
    {
        $languages = PageForm::languages();
        $default = Language::defaultCode();

        return Action::make('translate')
            ->label('Translate')
            ->icon('heroicon-m-language')
            ->color('gray')
            ->visible(fn (): bool => count($languages) > 1)
            ->modalHeading('Translate this page')
            ->modalDescription('Save your changes first. The languages you choose are re-translated from the saved source in the background, and you will be notified when they are ready — no need to edit each tab.')
            ->modalSubmitActionLabel('Translate')
            ->schema([
                Select::make('from')
                    ->label('Translate from')
                    ->options($languages)
                    ->default($default)
                    ->selectablePlaceholder(false)
                    ->required(),
                Select::make('to')
                    ->label('Into')
                    ->options($languages)
                    ->multiple()
                    ->default(array_values(array_diff(array_keys($languages), [$default])))
                    ->required()
                    ->helperText('Everything except the language you are translating from.'),
                Toggle::make('overwrite')
                    ->label('Replace wording that is already there')
                    ->default(true)
                    ->helperText('On: the chosen languages are re-translated from the source, so an edit you made once reaches them too. Off: only empty fields are filled and existing wording is kept.'),
            ])
            ->action(function (array $data): void {
                $this->queueTranslation(
                    (string) $data['from'],
                    array_map('strval', (array) $data['to']),
                    (bool) ($data['overwrite'] ?? false),
                );
            });
    }

    /**
     * @param  array<int, string>  $into
     */
    private function queueTranslation(string $from, array $into, bool $overwrite): void
    {
        $into = array_values(array_diff($into, [$from]));

        if ($into === []) {
            Notification::make()
                ->warning()
                ->title('Choose a language to translate into')
                ->body('Pick at least one language other than the one you are translating from.')
                ->send();

            return;
        }

        // The job translates the saved page, so an empty source here means the
        // editor has not written (or not yet saved) the language they chose.
        $source = PageTranslations::state($this->record)[$from] ?? [];

        if (app(PageTranslator::class)->texts($source) === []) {
            Notification::make()
                ->warning()
                ->title('Nothing to translate')
                ->body('The '.$from.' tab has no saved text yet. Write it and press Save, then translate.')
                ->send();

            return;
        }

        TranslatePageJob::dispatch(
            $this->record->getKey(),
            $from,
            $into,
            $overwrite,
            (int) auth()->id(),
        );

        Notification::make()
            ->success()
            ->title('Translation queued')
            ->body('It runs in the background. You will get a notification when the other languages are ready — reload the page to read them.')
            ->send();
    }

    /** Fill each language tab from its own translation row. */
    protected function mutateFormDataBeforeFill(array $data): array
    {
        $data['translations'] = PageTranslations::state($this->record);

        return $data;
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        $this->translations = PageTranslations::extract($data);

        return $data;
    }

    protected function afterSave(): void
    {
        PageTranslations::sync($this->record, $this->translations);
        $this->record->reorderGallery(array_map('intval', (array) ($this->data['gallery'] ?? [])));
    }
}
