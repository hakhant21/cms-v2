<?php

namespace Database\Seeders;

use App\Models\Setting;
use Illuminate\Database\Seeder;

/**
 * Starting values taken from the live site, so a fresh install already shows
 * the right company details and brand colours. Existing rows are left alone:
 * re-running the seeder must never overwrite what an admin edited.
 */
class SettingSeeder extends Seeder
{
    public function run(): void
    {
        $defaults = [
            'company' => [
                'company.name' => 'RUMA Organic Company',
                'company.email' => 'rumaorganic@rumaorg.com',
                'company.phone' => '09777538777',
                'company.phone_secondary' => '09781267752',
                'company.address' => 'No.4132, Yazahtarni Road, Bawgawaddy Ward, Pyinmana Tsp, Nay Pyi Taw',
                'company.latitude' => '19.7616782',
                'company.longitude' => '96.2196115',
                'company.country' => 'MM',
                // The listing's own id, from Google Maps → Share → Embed a map.
                // It is what makes the contact map show the business instead of
                // a bare pin, so it ships with the install rather than being
                // left for someone to discover.
                'company.map_place_id' => '0x30c8b9001d3e3443:0x7e68357b00be8b21',
                'company.map_query' => 'No.4132, Yazahtarni Road, Bawgawaddy Ward, Pyinmana Tsp, Nay Pyi Taw',
            ],
            'chat' => [
                // On from the first install, because a bubble with nobody behind
                // it is the thing to switch off — not the thing to remember to
                // switch on. Settings → Live chat turns it over either way.
                'chat.enabled' => true,
                'chat.greeting' => 'Hello! Tell us what you are looking for and we will help.',
            ],
            'theme' => [
                'theme.primary' => '#166534',
                'theme.accent' => '#bef264',
                'theme.surface' => '#fafaf9',
                'theme.text' => '#292524',
                'theme.animation' => 'dynamic',
                'theme.animation_duration' => '1200ms',
            ],
        ];

        foreach ($defaults as $category => $settings) {
            foreach ($settings as $key => $value) {
                if (Setting::query()->where('key', $key)->exists()) {
                    continue;
                }

                Setting::put($key, $value, $category);
            }
        }

        Setting::forgetCache();
    }
}
