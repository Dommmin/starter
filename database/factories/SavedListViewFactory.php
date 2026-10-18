<?php

namespace Database\Factories;

use App\Models\SavedListView;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SavedListView>
 */
class SavedListViewFactory extends Factory
{
    /**
     * Define the model's default state: drafts of the article list.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'list' => 'admin.articles.index',
            'name' => fake()->unique()->words(2, true),
            'query' => ['status' => 'draft'],
        ];
    }
}
