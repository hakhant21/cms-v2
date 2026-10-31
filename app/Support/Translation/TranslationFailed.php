<?php

namespace App\Support\Translation;

use RuntimeException;

/**
 * The translation service could not answer.
 *
 * Carries a sentence an editor can act on ("the key is missing", "the service
 * refused the request") rather than a stack trace, because the action shows it
 * in a notification.
 */
class TranslationFailed extends RuntimeException
{
    public static function notConfigured(): self
    {
        return new self('Machine translation is not set up yet: add GEMINI_API_KEY to the .env file.');
    }

    public static function service(string $reason): self
    {
        return new self('The translation service refused the request: '.$reason);
    }

    public static function unusableAnswer(): self
    {
        return new self('The translation service answered in a shape this site cannot read. Try again, or translate by hand.');
    }
}
