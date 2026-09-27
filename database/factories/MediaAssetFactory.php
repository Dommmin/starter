<?php

namespace Database\Factories;

use App\Enums\MediaStatus;
use App\Models\MediaAsset;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * Metadata only: tests that need real bytes put them on a faked disk.
 *
 * @extends Factory<MediaAsset>
 */
class MediaAssetFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $uuid = (string) Str::uuid();

        return [
            'uuid' => $uuid,
            'owner_id' => null,
            'disk' => 'media',
            'path' => "{$uuid}/original.jpg",
            'original_name' => fake()->slug(2).'.jpg',
            'mime' => 'image/jpeg',
            'size' => fake()->numberBetween(1_000, 500_000),
            'width' => 1600,
            'height' => 900,
            'checksum' => hash('sha256', $uuid),
            'status' => MediaStatus::Quarantine,
            'scan_error' => null,
            'alt' => null,
            'variants' => null,
        ];
    }

    public function clean(): static
    {
        return $this->state(fn (): array => ['status' => MediaStatus::Clean]);
    }

    public function rejected(): static
    {
        return $this->state(fn (): array => ['status' => MediaStatus::Rejected, 'scan_error' => 'Eicar-Signature']);
    }

    public function pdf(): static
    {
        return $this->state(fn (array $attributes): array => [
            'path' => "{$attributes['uuid']}/original.pdf",
            'original_name' => fake()->slug(2).'.pdf',
            'mime' => 'application/pdf',
            'width' => null,
            'height' => null,
        ]);
    }

    /**
     * A clean image with a fake variant set for 320 and 640 px.
     */
    public function withVariants(): static
    {
        return $this->clean()->state(function (array $attributes): array {
            $variants = [];

            foreach ([320, 640] as $width) {
                foreach (['avif', 'webp', 'jpg'] as $extension) {
                    $variants[] = [
                        'format' => $extension === 'jpg' ? 'jpeg' : $extension,
                        'width' => $width,
                        'height' => intdiv($width * 9, 16),
                        'path' => "media/{$attributes['uuid']}/abc123-{$width}.{$extension}",
                        'size' => 1_000,
                    ];
                }
            }

            return ['variants' => $variants];
        });
    }
}
