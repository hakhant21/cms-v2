<?php

namespace App\Support\Translation;

use App\Jobs\TranslatePageJob;
use App\Models\Language;
use App\Models\Page;
use App\Support\BlockStructure;
use App\Support\PageTranslations;
use Filament\Notifications\Notification;

/**
 * What happens when an editor saves a page: write once, read everywhere.
 *
 * The submitted languages are persisted, any locale the editor wrote by hand
 * is flagged so its wording survives, and — unless the page opted out — the
 * remaining languages are queued for machine translation from the default
 * language. An explicit Translate run is the way to hand a manual locale
 * back to the machine.
 */
final class AutoTranslation
{
    /**
     * @param  array<string, array<string, mixed>>  $submitted  the form's languages
     * @param  array<string, array<string, mixed>>  $baseline  the saved state before this save, empty when creating
     */
    public static function save(Page $page, array $submitted, array $baseline = []): void
    {
        PageTranslations::sync($page, $submitted);

        $default = Language::defaultCode();
        $translator = app(PageTranslator::class);
        $manual = self::manualLocales($translator, $submitted, $baseline, $default);

        if ($manual !== []) {
            $page->translations()->whereIn('locale', $manual)->update(['is_manual' => true]);
        }

        if (! $page->auto_translate) {
            return;
        }

        $into = array_values(array_diff(
            Language::activeCodes(),
            [$default],
            $manual,
            $page->translations()->where('is_manual', true)->pluck('locale')->all(),
        ));

        if ($into === []) {
            return;
        }

        // The job talks to the translation service, so a missing key is
        // reported here rather than as a job that can only ever fail.
        if (! $translator->isConfigured()) {
            Notification::make()
                ->warning()
                ->title('Auto-translate needs an API key')
                ->body('Add GEMINI_API_KEY to the .env file to fill the other languages automatically, or turn off "Auto-translate on save".')
                ->send();

            return;
        }

        // Nothing written in the default language yet: there is nothing to
        // translate, and an empty save should not make noise about it.
        if ($translator->texts($submitted[$default] ?? []) === []) {
            return;
        }

        TranslatePageJob::dispatch($page->getKey(), $default, $into, true, (int) auth()->id());

        Notification::make()
            ->success()
            ->title('Translation queued')
            ->body('The other languages are being translated from '.$default.' in the background. You will get a notification when they are ready — reload the page to read them.')
            ->send();
    }

    /**
     * The locales whose words the editor changed with this save.
     *
     * Only prose is compared, and it is compared in the SAVED structure's
     * order: the submitted blocks are re-fitted to the baseline first, so
     * reordering or deleting a section — in any tab — never reads as
     * hand-writing a language. Mirroring an image or adding a section does
     * not count either: a brand-new block is dropped by the re-fit, and a
     * deleted one is filled back in with its saved words, leaving nothing
     * to flag. Only a real change of wording marks a locale manual.
     *
     * While creating, a locale with nothing saved yet is measured against the
     * default language instead, so typing real Burmese into a fresh tab is
     * honoured while the copied English is not — and fields left empty (the
     * title and summary are not mirrored) stay with the machine. An existing
     * page whose locale is still empty is simply waiting to be translated.
     *
     * @param  array<string, array<string, mixed>>  $submitted
     * @param  array<string, array<string, mixed>>  $baseline
     * @return array<int, string>
     */
    private static function manualLocales(PageTranslator $translator, array $submitted, array $baseline, string $default): array
    {
        $creating = $baseline === [];
        $manual = [];

        foreach (Language::activeCodes() as $code) {
            if ($code === $default) {
                continue;
            }

            $before = $translator->texts($baseline[$code] ?? []);

            if ($creating && $before === []) {
                $before = $translator->texts($submitted[$default] ?? []);
            }

            $after = $translator->texts(self::inSavedStructure($submitted[$code] ?? [], $baseline[$code] ?? []));

            foreach ($before as $path => $value) {
                // A path the submission no longer has is structure leaving
                // the page (a deleted field or row), not a word rewritten.
                if (! array_key_exists($path, $after)) {
                    continue;
                }

                if ($after[$path] !== $value) {
                    $manual[] = $code;

                    break;
                }
            }
        }

        return $manual;
    }

    /**
     * The submission wearing the saved structure, so wording can be compared
     * path by path no matter how the sections were shuffled meanwhile.
     *
     * @param  array<string, mixed>  $submitted
     * @param  array<string, mixed>  $baseline
     * @return array<string, mixed>
     */
    private static function inSavedStructure(array $submitted, array $baseline): array
    {
        $savedBlocks = $baseline['content_blocks'] ?? null;
        $blocks = $submitted['content_blocks'] ?? null;

        if (! is_array($savedBlocks) || $savedBlocks === [] || ! is_array($blocks)) {
            return $submitted;
        }

        $submitted['content_blocks'] = BlockStructure::align($savedBlocks, $blocks);

        return $submitted;
    }
}
