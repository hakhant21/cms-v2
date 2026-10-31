<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call([
            LanguageSeeder::class,
            SettingSeeder::class,
            SiteContentSeeder::class,
            FooterNavigationSeeder::class,
        ]);

        // Only auto-create the local admin so a production install never ships
        // with a known password. Real accounts are created from the panel.
        if (app()->environment('local', 'testing')) {
            User::query()->firstOrCreate(
                ['email' => 'admin@rumaorg.com'],
                ['name' => 'Site Admin', 'password' => 'password', 'is_admin' => true],
            );
        }
    }
}
