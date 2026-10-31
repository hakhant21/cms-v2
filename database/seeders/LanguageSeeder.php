<?php

namespace Database\Seeders;

use App\Models\Language;
use Illuminate\Database\Seeder;

/**
 * The three locales the site ships with. Keeping English flagged as default
 * means every missing translation resolves to English without extra config.
 */
class LanguageSeeder extends Seeder
{
    public function run(): void
    {
        $languages = [
            ['code' => 'en', 'name' => 'English', 'native_name' => 'English', 'lang_country' => 'en-GB', 'is_default' => true, 'sort_order' => 1],
            ['code' => 'my', 'name' => 'Myanmar', 'native_name' => 'မြန်မာ', 'lang_country' => 'my-MM', 'is_default' => false, 'sort_order' => 2],
            ['code' => 'ru', 'name' => 'Russian', 'native_name' => 'Русский', 'lang_country' => 'ru-RU', 'is_default' => false, 'sort_order' => 3],
        ];

        foreach ($languages as $language) {
            Language::query()->updateOrCreate(
                ['code' => $language['code']],
                $language + ['direction' => 'ltr', 'is_active' => true],
            );
        }

        Language::forgetCache();
    }
}
