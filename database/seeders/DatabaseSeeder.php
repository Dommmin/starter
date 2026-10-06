<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database. Idempotent: a second run adds nothing.
     * The sample administrator and the sample content of every module are
     * created only in the local environment; menus and home sections exist
     * everywhere.
     */
    public function run(): void
    {
        if (app()->environment('local')) {
            if (! User::query()->where('email', DemoContent::USER_EMAIL)->exists()) {
                User::factory()->admin()->create([
                    'name' => DemoContent::USER_NAME,
                    'email' => DemoContent::USER_EMAIL,
                ]);
            }

            $this->call([
                DemoUserSeeder::class,
                DemoMediaSeeder::class,
                PageSeeder::class,
                ArticleSeeder::class,
                FaqSeeder::class,
                ContactMessageSeeder::class,
                SiteSettingsSeeder::class,
            ]);
        }

        $this->call(NavigationMenuSeeder::class);
        $this->call(HomeSectionSeeder::class);

        if (app()->environment('local')) {
            $this->call(DemoNavigationSeeder::class);
        }
    }
}
