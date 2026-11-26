<?php

use App\Jobs\GenerateImageVariants;
use App\Models\MediaAsset;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Tests\Fakes\MediaFixtures;

beforeEach(function () {
    Storage::fake('media');
    Storage::fake('public');
});

test('the command queues every clean image and nothing else', function () {
    Queue::fake();
    $withVariants = MediaAsset::factory()->withVariants()->create();
    $withoutVariants = MediaAsset::factory()->clean()->create();
    MediaAsset::factory()->create();
    MediaAsset::factory()->rejected()->create();
    MediaAsset::factory()->clean()->pdf()->create();

    $this->artisan('media:regenerate-variants')
        ->expectsOutputToContain('2 image(s) queued')
        ->assertSuccessful();

    Queue::assertPushed(GenerateImageVariants::class, 2);
    Queue::assertPushed(GenerateImageVariants::class, fn (GenerateImageVariants $job): bool => $job->mediaAssetId === $withVariants->id);
    Queue::assertPushed(GenerateImageVariants::class, fn (GenerateImageVariants $job): bool => $job->mediaAssetId === $withoutVariants->id);
});

test('with --missing only images without variants are queued', function () {
    Queue::fake();
    MediaAsset::factory()->withVariants()->create();
    $withoutVariants = MediaAsset::factory()->clean()->create();

    $this->artisan('media:regenerate-variants', ['--missing' => true])->assertSuccessful();

    Queue::assertPushed(GenerateImageVariants::class, 1);
    Queue::assertPushed(GenerateImageVariants::class, fn (GenerateImageVariants $job): bool => $job->mediaAssetId === $withoutVariants->id);
});

test('with --sync the variants are generated in the process', function () {
    $asset = MediaAsset::factory()->clean()->create();
    Storage::disk('media')->put($asset->path, MediaFixtures::jpegBytes());

    $this->artisan('media:regenerate-variants', ['--sync' => true])
        ->expectsOutputToContain('1 image(s) regenerated')
        ->assertSuccessful();

    expect($asset->refresh()->variants)->not->toBeEmpty();
});
