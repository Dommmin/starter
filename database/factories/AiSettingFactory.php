<?php

namespace Database\Factories;

use App\Enums\AiProvider;
use App\Models\AiSetting;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AiSetting>
 */
class AiSettingFactory extends Factory
{
    /**
     * Define the model's default state: the singleton row with a synthetic
     * OpenAI key.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'id' => AiSetting::SINGLETON_ID,
            'provider' => AiProvider::OpenAi,
            'api_key' => 'synthetic-panel-key',
            'updated_by' => null,
        ];
    }
}
