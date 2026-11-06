<?php

namespace App\Jobs;

use App\Contracts\Translator;
use App\Models\NavigationItem;
use App\Models\NavigationItemTranslation;
use App\Models\User;
use App\Support\Translation\TranslationFailed;
use Filament\Notifications\Notification;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Throwable;

/**
 * Translates one menu label into the machine-owned languages and writes the
 * rows straight into the navigation item.
 *
 * A label is a single short string per language, so the job stays small; it
 * runs on the queue for the same reason the page job does — the editor is
 * freed immediately and gets a bell notification when the labels land.
 */
class TranslateNavigationLabelJob implements ShouldQueue
{
    use Queueable;

    public int $timeout = 120;

    public int $tries = 1;

    /**
     * @param  array<int, string>  $into  the locales to write
     */
    public function __construct(
        public readonly int $navigationItemId,
        public readonly string $from,
        public readonly string $label,
        public readonly array $into,
        public readonly int $userId,
    ) {}

    public function handle(Translator $translator): void
    {
        $item = NavigationItem::query()->find($this->navigationItemId);

        if ($item === null) {
            return;
        }

        $failed = [];
        $done = [];

        foreach ($this->into as $locale) {
            if ($locale === $this->from) {
                continue;
            }

            try {
                $translated = $translator->translate([$this->label], $this->from, $locale)[0] ?? null;
            } catch (TranslationFailed $exception) {
                // One language failing must not lose the ones already done.
                $failed[] = strtoupper($locale).': '.$exception->getMessage();

                continue;
            }

            if (! is_string($translated) || trim($translated) === '') {
                continue;
            }

            // A row the machine writes is machine-owned: the next label
            // change refreshes it again.
            NavigationItemTranslation::query()->updateOrCreate(
                ['navigation_item_id' => $item->getKey(), 'locale' => $locale],
                ['label' => trim($translated), 'is_manual' => false],
            );

            $done[] = $locale;
        }

        if ($done !== []) {
            $this->notify(
                'Menu label translated',
                '“'.$this->label.'” is now available in '.implode(', ', $done).'.'.($failed === [] ? '' : ' Some languages failed.'),
                $failed === [] ? 'success' : 'warning',
            );
        }

        if ($failed !== []) {
            $this->notify(
                'Some labels were not translated',
                implode(' ', array_unique($failed)),
                'danger',
            );
        }
    }

    /** An unexpected crash still tells the editor, rather than vanishing. */
    public function failed(Throwable $exception): void
    {
        $this->notify(
            'Label translation failed',
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
