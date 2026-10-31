<?php

namespace App\Filament\Resources\Pages\Pages;

use App\Filament\Resources\Pages\PageResource;
use App\Filament\Resources\Pages\Schemas\PageForm;
use App\Models\Language;
use App\Models\Page;
use App\Support\PageTranslations;
use App\Support\Translation\PageTranslator;
use App\Support\Translation\TranslationFailed;
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
     * Machine translation, filled into the tabs rather than saved.
     *
     * The result lands in the form, not in the database: a machine translation
     * becomes content only once an editor has read it. Every tab it touched is
     * waiting for review, and nothing is published until the page is saved.
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
            ->modalDescription('The words are copied into the other language tabs. Read them before you save.')
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
                    ->label('Replace text that is already there')
                    ->helperText('Off: only empty fields are filled, and your own wording is kept.'),
            ])
            ->action(function (array $data): void {
                $this->translateTabs(
                    (string) $data['from'],
                    array_map('strval', (array) $data['to']),
                    (bool) ($data['overwrite'] ?? false),
                );
            });
    }

    /**
     * @param  array<int, string>  $into
     */
    private function translateTabs(string $from, array $into, bool $overwrite): void
    {
        $into = array_values(array_diff($into, [$from]));
        $state = is_array($this->data['translations'] ?? null) ? $this->data['translations'] : [];
        $source = is_array($state[$from] ?? null) ? $state[$from] : [];

        $translator = app(PageTranslator::class);

        if ($translator->texts($source) === []) {
            Notification::make()
                ->warning()
                ->title('Nothing to translate')
                ->body('The tab you chose has no text in it yet.')
                ->send();

            return;
        }

        $translated = 0;
        $failed = [];

        foreach ($into as $locale) {
            try {
                $result = $translator->translatePage(
                    $source,
                    is_array($state[$locale] ?? null) ? $state[$locale] : [],
                    $from,
                    $locale,
                    $overwrite,
                );
            } catch (TranslationFailed $exception) {
                // A missing key or a refused request stops that language only;
                // the ones already done stay in the form.
                $failed[] = $exception->getMessage();

                continue;
            }

            $state[$locale] = $result['state'];
            $translated += $result['translated'];
        }

        if ($translated > 0) {
            $this->data['translations'] = $state;
            $this->form->fill($this->data);
        }

        if ($failed !== []) {
            Notification::make()
                ->danger()
                ->title('Some languages were not translated')
                ->body(implode(' ', array_unique($failed)))
                ->persistent()
                ->send();
        }

        if ($translated > 0) {
            Notification::make()
                ->success()
                ->title($translated.' strings translated')
                ->body('Read the other language tabs, then press Save to keep them.')
                ->send();
        }
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
