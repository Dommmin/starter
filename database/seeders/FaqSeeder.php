<?php

namespace Database\Seeders;

use App\Models\Faq;
use Illuminate\Database\Seeder;

/**
 * Local sample faqs built from FaqFactory.
 */
class FaqSeeder extends Seeder
{
    public function run(): void
    {
        Faq::factory()->count(10)->create();
    }
}
