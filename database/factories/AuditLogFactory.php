<?php

namespace Database\Factories;

use App\Enums\AuditAction;
use App\Models\AuditLog;
use App\Models\Page;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AuditLog>
 */
class AuditLogFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'actor_id' => User::factory(),
            'action' => AuditAction::PageUpdated,
            'subject_type' => (new Page)->getMorphClass(),
            'subject_id' => fake()->numberBetween(1, 1000),
            'changes' => ['en.title' => ['old' => 'Old title', 'new' => 'New title']],
        ];
    }

    /**
     * An entry recorded by a console command (no authenticated actor).
     */
    public function system(): static
    {
        return $this->state(fn (): array => ['actor_id' => null]);
    }
}
