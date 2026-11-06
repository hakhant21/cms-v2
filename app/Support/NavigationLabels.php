<?php

namespace App\Support;

use App\Contracts\Translator;
use App\Jobs\TranslateNavigationLabelJob;
use App\Models\Language;
use App\Models\NavigationItem;
use App\Models\NavigationItemTranslation;
use Filament\Notifications\Notification;
use Illuminate\Support\Collection;

/**
 * Write-once labels for header and footer links.
 *
 * The editor writes the label in one language — whichever they prefer — and
 * saving translates it into every other language in the background. A label
 * typed into a language's own row is hand-written: it is flagged and kept
 * until the editor changes it, exactly like a page's manual locale.
 */
final class NavigationLabels
{
    /**
     * Flag the labels the editor wrote and queue the machine-owned ones.
     *
     * @param  Collection<string, NavigationItemTranslation>  $saved  the stored rows as they were before this save, keyed by locale
     */
    public static function sync(NavigationItem $item, Collection $saved): void
    {
        $current = $item->translations()->get()->keyBy('locale');

        // A row the editor added or reworded is hand-written from now on;
        // an untouched row keeps whatever ownership it already had.
        foreach ($current as $locale => $row) {
            $before = $saved->get($locale);

            if ($before === null || trim((string) $before->label) !== trim((string) $row->label)) {
                $row->forceFill(['is_manual' => true])->save();
            }
        }

        // The label every other language is translated from: the default
        // language's row when there is one, otherwise any hand-written row.
        $source = $current->get(Language::defaultCode())
            ?? $current->firstWhere('is_manual', true)
            ?? $current->first();

        if ($source === null || trim((string) $source->label) === '') {
            return;
        }

        $into = array_values(array_diff(
            Language::activeCodes(),
            [$source->locale],
            $current->where('is_manual', true)->pluck('locale')->all(),
        ));

        if ($into === []) {
            return;
        }

        // The job talks to the translation service, so a missing key is
        // reported here rather than as a job that can only ever fail.
        if (! app(Translator::class)->isConfigured()) {
            Notification::make()
                ->warning()
                ->title('Auto-translate needs an API key')
                ->body('Add GEMINI_API_KEY to the .env file to fill the other languages automatically. Until then they fall back to the label you wrote.')
                ->send();

            return;
        }

        TranslateNavigationLabelJob::dispatch(
            $item->getKey(),
            $source->locale,
            (string) $source->label,
            $into,
            (int) auth()->id(),
        );

        Notification::make()
            ->success()
            ->title('Label translation queued')
            ->body('The menu label is being translated into '.implode(', ', $into).' in the background. You will get a notification when it is ready.')
            ->send();
    }
}
