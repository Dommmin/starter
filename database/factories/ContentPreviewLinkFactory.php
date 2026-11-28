<?php

namespace Database\Factories;

use App\Models\ContentPreviewLink;
use App\Models\Page;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<ContentPreviewLink>
 */
class ContentPreviewLinkFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'subject_type' => (new Page)->getMorphClass(),
            'subject_id' => Page::factory(),
            'locale' => 'en',
            'token_hash' => ContentPreviewLink::hashToken(Str::random(40)),
            'created_by' => null,
            'expires_at' => now()->addWeek(),
        ];
    }
}
