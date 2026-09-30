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
        // User::factory(10)->create();

        User::factory()->admin()->create([
            'name' => DemoContent::USER_NAME,
            'email' => DemoContent::USER_EMAIL,
        ]);

        if (app()->environment('local')) {
            $this->call(PageSeeder::class);
            $this->call(ArticleSeeder::class);
        }

        $this->call(NavigationMenuSeeder::class);
        $this->call(HomeSectionSeeder::class);
    }
}
