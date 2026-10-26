<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

/**
 * Fill a public showcase server (any environment) with the starter samples
 * and the software house content in one explicit run:
 * `php artisan db:seed --class=SoftwareHouseShowcaseSeeder --force`.
 *
 * Unlike the local demo it creates no sample accounts with the known
 * password: the content belongs to the sample administrator, whose random
 * password nobody knows. Idempotent like the seeders it calls.
 */
class SoftwareHouseShowcaseSeeder extends Seeder
{
    public function run(): void
    {
        config(['demo.samples' => true]);

        $this->call([DatabaseSeeder::class, SoftwareHouseSeeder::class]);
    }
}
