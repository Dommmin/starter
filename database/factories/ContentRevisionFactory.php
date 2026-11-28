<?php

namespace Database\Factories;

use App\Models\ContentRevision;
use App\Models\Page;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ContentRevision>
 */
class ContentRevisionFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'subject_type' => (new Page)->getMorphClass(),
            'subject_id' => Page::factory(),
            'author_id' => null,
            'snapshot' => ['translations' => []],
        ];
    }
}
