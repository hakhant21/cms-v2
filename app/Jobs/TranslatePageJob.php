<?php

namespace App\Jobs;

use App\Models\Page;
use App\Models\User;
use App\Support\PageTranslations;
use App\Support\Translation\PageTranslator;
use App\Support\Translation\TranslationFailed;
use Filament\Notifications\Notification;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Throwable;

/**
 * Translates a page in the background and writes the result straight into its
 * language rows.
 *
 * Talking to Gemini takes a request per batch of strings, so a long page can
 * hold an HTTP connection open for minutes. Running it on the queue (Horizon)
 * frees the editor immediately: the job reads the saved source language, fills
 * the chosen targets and notifies whoever asked for it. The translation is
 * live content once it lands, so the notification points back at the page to
 * read over it.
 */
class TranslatePageJob implements ShouldQueue
{
    use Queueable;

    /** One run may span several Gemini batches; give it room to finish. */
    public int $timeout = 600;

    /** A refused or malformed answer will not improve on a retry. */
    public int $tries = 1;

    /**
     * @param  array<int, string>  $into
     */
    public function __construct(
        public readonly int $pageId,
        public readonly string $from,
        public readonly array $into,
        public readonly bool $overwrite,
        public readonly int $userId,
    ) {}

    public function handle(PageTranslator $translator): void
    {
        $page = Page::query()->find($this->pageId);

        if ($page === null) {
            return;
        }

        $state = PageTranslations::state($page);
        $source = $state[$this->from] ?? [];

        if ($translator->texts($source) === []) {
            $this->notify(
                'Nothing to translate',
                'The '.$this->from.' version of “'.$page->localizedTitle($this->from).'” has no text in it yet.',
                'warning',
            );

            return;
        }

        $translated = 0;
        $failed = [];

        foreach ($this->into as $locale) {
            if ($locale === $this->from) {
                continue;
            }

            try {
                $result = $translator->translatePage(
                    $source,
                    $state[$locale] ?? [],
                    $this->from,
                    $locale,
                    $this->overwrite,
                );
            } catch (TranslationFailed $exception) {
                // One language failing must not lose the ones already done.
                $failed[] = strtoupper($locale).': '.$exception->getMessage();

                continue;
            }

            $state[$locale] = $result['state'];
            $translated += $result['translated'];
        }

        // Writing the whole state back keeps the source and any untouched
        // language exactly as they were; only the targets change.
        PageTranslations::sync($page, $state);

        $title = $page->localizedTitle($this->from);

        if ($translated > 0) {
            $this->notify(
                $translated.' strings translated',
                '“'.$title.'” is now available in the languages you chose. Open the page to read them over.',
                $failed === [] ? 'success' : 'warning',
            );
        }

        if ($failed !== []) {
            $this->notify(
                'Some languages were not translated',
                implode(' ', array_unique($failed)),
                'danger',
            );
        }
    }

    /** An unexpected crash still tells the editor, rather than vanishing. */
    public function failed(Throwable $exception): void
    {
        $this->notify(
            'Translation failed',
            $exception->getMessage(),
            'danger',
        );
    }

    private function notify(string $title, string $body, string $status): void
    {
        $user = User::query()->find($this->userId);

        if ($user === null) {
            return;
        }

        $notification = Notification::make()
            ->title($title)
            ->body($body);

        match ($status) {
            'success' => $notification->success(),
            'warning' => $notification->warning(),
            default => $notification->danger(),
        };

        $notification->sendToDatabase($user);
    }
}
