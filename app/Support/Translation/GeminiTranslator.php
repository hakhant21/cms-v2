<?php

namespace App\Support\Translation;

use App\Contracts\Translator;
use App\Models\Language;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;

/**
 * Translation through Google's Gemini API.
 *
 * The whole batch travels as one JSON document, so the answer comes back as a
 * list of the same length and can be matched to the input by position — no
 * fragile "split the reply on newlines" guessing. Long pages are split into
 * chunks by the caller's batch size.
 */
final class GeminiTranslator implements Translator
{
    public function isConfigured(): bool
    {
        return trim((string) config('ai.gemini.key')) !== '';
    }

    public function translate(array $texts, string $from, string $to): array
    {
        if (! $this->isConfigured()) {
            throw TranslationFailed::notConfigured();
        }

        if ($texts === []) {
            return [];
        }

        $keys = array_keys($texts);
        $results = [];

        foreach (array_chunk(array_values($texts), max(1, (int) config('ai.batch', 40))) as $chunk) {
            foreach ($this->request($chunk, $from, $to) as $value) {
                $results[] = $value;
            }
        }

        // The keys given by the caller (field paths, usually) are put back so
        // the answer can be written straight into the page.
        $ordered = [];

        foreach ($keys as $index => $key) {
            $ordered[$key] = $results[$index] ?? $texts[$key];
        }

        return $ordered;
    }

    /**
     * @param  array<int, string>  $texts
     * @return array<int, string>
     */
    private function request(array $texts, string $from, string $to): array
    {
        $payload = [
            'contents' => [[
                'parts' => [['text' => $this->prompt($texts, $from, $to)]],
            ]],
            'generationConfig' => [
                'responseMimeType' => 'application/json',
                'temperature' => 0.2,
            ],
        ];

        try {
            $response = Http::withHeaders([
                'x-goog-api-key' => (string) config('ai.gemini.key'),
            ])
                ->timeout((int) config('ai.timeout', 60))
                ->acceptJson()
                ->post($this->endpoint(), $payload);
        } catch (ConnectionException $exception) {
            throw TranslationFailed::service($exception->getMessage());
        }

        if ($response->failed()) {
            throw TranslationFailed::service($response->status().' '.$response->reason());
        }

        $raw = $response->json('candidates.0.content.parts.0.text');

        if (! is_string($raw)) {
            throw TranslationFailed::unusableAnswer();
        }

        $decoded = json_decode($raw, true);

        if (! is_array($decoded) || ! isset($decoded['translations']) || ! is_array($decoded['translations'])) {
            throw TranslationFailed::unusableAnswer();
        }

        $values = array_values($decoded['translations']);

        if (count($values) !== count($texts)) {
            throw TranslationFailed::unusableAnswer();
        }

        return array_map(
            fn (mixed $value, string $original): string => is_string($value) && trim($value) !== '' ? $value : $original,
            $values,
            $texts,
        );
    }

    /**
     * The instruction sent with the batch.
     *
     * Written as a translation brief rather than a chat turn: names, numbers,
     * HTML and placeholders must survive untouched, because a translated
     * product name or a mangled link is worse than an untranslated string.
     *
     * @param  array<int, string>  $texts
     */
    private function prompt(array $texts, string $from, string $to): string
    {
        $source = $this->languageName($from);
        $target = $this->languageName($to);
        $count = count($texts);

        $json = json_encode(array_values($texts), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        return <<<PROMPT
        You are translating the content of an organic-farm website from {$source} into {$target}.

        Rules:
        - Translate the meaning, keep the tone: warm, plain, and specific to farming.
        - Leave brand names, product names, numbers, units, e-mail addresses, URLs, HTML tags and anything in braces such as {name} exactly as they are.
        - Keep the length close to the original; these strings go into fixed layout slots.
        - Never merge, split or reorder values. Return exactly one translation per input.

        Reply with JSON only, in this shape: {"translations": ["...", "..."]}

        Values (JSON array of {$count} strings):
        {$json}
        PROMPT;
    }

    private function languageName(string $code): string
    {
        $name = Language::query()->where('code', $code)->value('name');

        return is_string($name) && $name !== '' ? "{$name} ({$code})" : $code;
    }

    private function endpoint(): string
    {
        $base = rtrim((string) config('ai.gemini.endpoint', 'https://generativelanguage.googleapis.com'), '/');
        $model = (string) config('ai.gemini.model', 'gemini-2.5-flash');

        return "{$base}/v1beta/models/{$model}:generateContent";
    }
}
