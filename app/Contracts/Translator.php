<?php

namespace App\Contracts;

use App\Support\Translation\TranslationFailed;

/**
 * Something that can turn text from one language into another.
 *
 * Kept as a batch on purpose: a page holds dozens of short strings, and a
 * request per string would take minutes. Implementations return one value per
 * input, so callers can map the result back by position.
 */
interface Translator
{
    /**
     * @param  array<int, string>  $texts
     * @return array<int, string>
     *
     * @throws TranslationFailed
     */
    public function translate(array $texts, string $from, string $to): array;

    /** Whether this translator has everything it needs to run. */
    public function isConfigured(): bool;
}
