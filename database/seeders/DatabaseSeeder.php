<?php

namespace Database\Seeders;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database. Idempotent: a second run adds nothing.
     * The sample administrator and the sample content of every module are
     * created only in the local environment, on the public demo and during a
     * showcase seed (without the sample accounts); legal page templates,
     * contact forms, menus and home sections exist everywhere.
     */
    public function run(): void
    {
        if (DemoContent::samplesEnabled()) {
            if (! User::query()->where('email', DemoContent::USER_EMAIL)->exists()) {
                // No factory: the public demo runs without dev dependencies (Faker).
                (new User)->forceFill([
                    'name' => DemoContent::USER_NAME,
                    'email' => DemoContent::USER_EMAIL,
                    'email_verified_at' => now(),
                    'password' => Str::password(32),
                    'role' => UserRole::Admin,
                ])->save();
            }

            if (DemoContent::sampleAccountsEnabled()) {
                $this->call(DemoUserSeeder::class);
            }

            $this->call([
                DemoMediaSeeder::class,
                PageSeeder::class,
                ArticleSeeder::class,
                TagSeeder::class,
                FaqSeeder::class,
                ContactMessageSeeder::class,
                SiteSettingsSeeder::class,
            ]);
        }

        $this->call(LegalPageSeeder::class);
        $this->call(ContactFormSeeder::class);
        $this->call(NavigationMenuSeeder::class);
        $this->call(HomeSectionSeeder::class);

        if (DemoContent::samplesEnabled()) {
            $this->call(DemoNavigationSeeder::class);
        }
    }
}
