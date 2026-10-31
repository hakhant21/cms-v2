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

        User::query()->firstOrCreate(
            ['email' => 'admin@rumaorg.com'],
            ['name' => 'Site Admin', 'password' => '0yUsUaZB5r9I', 'is_admin' => true],
        );
    }
}
