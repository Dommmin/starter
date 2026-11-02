<?php

namespace Database\Seeders;

use App\Models\Redirect;
use Illuminate\Database\Seeder;

/**
 * Local sample redirects of a former site built from RedirectFactory.
 */
class RedirectSeeder extends Seeder
{
    public function run(): void
    {
        Redirect::factory()->count(5)->create();
        Redirect::factory()->inactive()->create();
    }
}
