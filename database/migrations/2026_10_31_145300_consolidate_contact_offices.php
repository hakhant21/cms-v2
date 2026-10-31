<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * The contact page used to carry its office addresses as a `plain` section's
 * rich_text, copied into every language — so one address change meant editing
 * English, Myanmar and Russian separately. The offices now live once in
 * Settings (company.offices) and the contact_details block renders them in
 * every language with translated labels.
 *
 * This strips the now-redundant address sections from the contact page's
 * translations. The default office data itself is filled by SettingSeeder,
 * which runs on every boot and only adds keys that are missing.
 */
return new class extends Migration
{
    public function up(): void
    {
        $pageId = DB::table('pages')->where('slug', 'contact')->value('id');

        if ($pageId === null) {
            return;
        }

        $translations = DB::table('page_translations')->where('page_id', $pageId)->get();

        foreach ($translations as $translation) {
            $blocks = json_decode((string) $translation->content_blocks, true);

            if (! is_array($blocks)) {
                continue;
            }

            $kept = array_values(array_filter($blocks, fn (mixed $block): bool => ! $this->isOfficeAddressBlock($block)));

            if (count($kept) === count($blocks)) {
                continue;
            }

            DB::table('page_translations')
                ->where('id', $translation->id)
                ->update(['content_blocks' => json_encode($kept, JSON_UNESCAPED_UNICODE)]);
        }
    }

    /**
     * A plain section whose rich_text is the duplicated office address. Scoped
     * to the contact page above, and matched on the address markers so a normal
     * intro section is never touched. An empty plain section is leftover
     * scaffolding from the same import and is cleared too.
     */
    private function isOfficeAddressBlock(mixed $block): bool
    {
        if (! is_array($block) || ($block['type'] ?? null) !== 'section') {
            return false;
        }

        if (($block['data']['layout'] ?? null) !== 'plain') {
            return false;
        }

        $fields = $block['data']['fields'] ?? [];
        $fields = is_array($fields) ? $fields : [];

        $hasText = false;

        foreach ($fields as $field) {
            if (! is_array($field)) {
                continue;
            }

            $value = (string) ($field['value'] ?? '');

            if (trim($value) === '') {
                continue;
            }

            $hasText = true;

            // str_contains rather than a /u regex: a stray byte in the legacy
            // Burmese text must not make the match silently fail.
            foreach (['RUMA', 'Address', 'Tax ID', 'Наш офис', 'ရုံး'] as $marker) {
                if (str_contains($value, $marker)) {
                    return true;
                }
            }
        }

        return ! $hasText;
    }

    public function down(): void
    {
        // The removed text is superseded by Settings; there is nothing to put
        // back that the contact_details block does not already render.
    }
};
