<?php

use App\Enums\AuditAction;
use App\Enums\MediaStatus;
use App\Jobs\GenerateImageVariants;
use App\Jobs\ScanMediaAsset;
use App\Models\AuditLog;
use App\Models\MediaAsset;
use App\Services\Media\Exceptions\MalwareScannerUnavailable;
use App\Services\Media\MalwareScanner;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Tests\Fakes\FakeMalwareScanner;
use Tests\Fakes\MediaFixtures;

beforeEach(function () {
    Storage::fake('media');
    Storage::fake('public');
});

/**
 * A quarantined asset with real bytes on the faked private disk.
 *
 * @param  array<string, mixed>  $attributes
 */
function quarantinedMedia(string $bytes, array $attributes = []): MediaAsset
{
    $asset = MediaAsset::factory()->create($attributes);
    Storage::disk('media')->put($asset->path, $bytes);

    return $asset;
}

function runMediaScan(MediaAsset $asset, MalwareScanner $scanner): void
{
    app()->instance(MalwareScanner::class, $scanner);
    app()->call([new ScanMediaAsset($asset->id), 'handle']);
}

test('a clean scan marks the asset clean, audits it and queues image variants', function () {
    Queue::fake();
    $scanner = FakeMalwareScanner::clean();
    $asset = quarantinedMedia(MediaFixtures::jpegBytes());

    runMediaScan($asset, $scanner);

    expect($asset->refresh()->status)->toBe(MediaStatus::Clean)
        ->and($asset->scan_error)->toBeNull()
        ->and($scanner->scanned)->toBe([Storage::disk('media')->get($asset->path)]);
    Queue::assertPushed(GenerateImageVariants::class, fn (GenerateImageVariants $job): bool => $job->mediaAssetId === $asset->id);

    $audit = AuditLog::query()->where('action', AuditAction::MediaCleaned)->sole();
    expect($audit->actor_id)->toBeNull()
        ->and($audit->changes)->toBe(['status' => ['old' => 'quarantine', 'new' => 'clean']]);
});

test('a clean document gets no variants', function () {
    Queue::fake();
    $asset = quarantinedMedia(MediaFixtures::pdfBytes(), ['mime' => 'application/pdf', 'path' => 'doc/original.pdf']);

    runMediaScan($asset, FakeMalwareScanner::clean());

    expect($asset->refresh()->status)->toBe(MediaStatus::Clean);
    Queue::assertNotPushed(GenerateImageVariants::class);
});

test('an infected file is rejected and its bytes are deleted', function () {
    Queue::fake();
    $asset = quarantinedMedia(MediaFixtures::jpegBytes());

    runMediaScan($asset, FakeMalwareScanner::infected('Eicar-Signature'));

    expect($asset->refresh()->status)->toBe(MediaStatus::Rejected)
        ->and($asset->scan_error)->toBe('Malware detected: Eicar-Signature');
    Storage::disk('media')->assertMissing($asset->path);
    expect(Storage::disk('public')->allFiles())->toBe([]);
    Queue::assertNotPushed(GenerateImageVariants::class);
    expect(AuditLog::query()->where('action', AuditAction::MediaRejected)->count())->toBe(1);
});

test('an unavailable scanner keeps the file in quarantine and retries with backoff', function () {
    Queue::fake();
    $asset = quarantinedMedia(MediaFixtures::jpegBytes());

    expect(fn () => runMediaScan($asset, FakeMalwareScanner::unavailable()))
        ->toThrow(MalwareScannerUnavailable::class);

    expect($asset->refresh()->status)->toBe(MediaStatus::Quarantine)
        ->and($asset->scan_error)->toBe('clamd connection failed (111).');
    Storage::disk('media')->assertExists($asset->path);
    Queue::assertNotPushed(GenerateImageVariants::class);
    expect(AuditLog::query()->count())->toBe(0);

    $job = new ScanMediaAsset($asset->id);
    expect($job->tries)->toBe(5)
        ->and($job->backoff())->toBe([30, 120, 300, 900]);

    $job->failed(new MalwareScannerUnavailable('down'));
    expect($asset->refresh()->status)->toBe(MediaStatus::Quarantine);
});

test('the scan is idempotent for assets that left quarantine', function () {
    Queue::fake();
    $scanner = FakeMalwareScanner::infected();
    $asset = MediaAsset::factory()->clean()->create();

    runMediaScan($asset, $scanner);

    expect($asset->refresh()->status)->toBe(MediaStatus::Clean)
        ->and($scanner->scanned)->toBe([]);
});

test('variants are generated in avif, webp and jpeg without upscaling or exif', function () {
    $asset = quarantinedMedia(MediaFixtures::jpegBytes(700, 350, withExif: true), ['width' => 700, 'height' => 350]);
    $original = Storage::disk('media')->path($asset->path);
    expect(exif_read_data($original)['Orientation'] ?? null)->toBe(6);
    $asset->forceFill(['status' => MediaStatus::Clean])->save();

    app()->call([new GenerateImageVariants($asset->id), 'handle']);

    $asset->refresh();
    $variants = collect($asset->variants);

    // Orientation 6 rotates the 700×350 source to 350×700.
    expect($asset->width)->toBe(350)
        ->and($asset->height)->toBe(700)
        ->and($variants->pluck('width')->unique()->sort()->values()->all())->toBe([320, 350])
        ->and($variants->pluck('format')->unique()->sort()->values()->all())->toBe(['avif', 'jpeg', 'webp'])
        ->and($variants)->toHaveCount(6);

    foreach ($variants as $variant) {
        expect($variant['path'])->toMatch("#^media/{$asset->uuid}/[0-9a-f]{16}-{$variant['width']}\\.(avif|webp|jpg)$#");
        Storage::disk('public')->assertExists($variant['path']);

        $absolute = Storage::disk('public')->path($variant['path']);
        $info = getimagesize($absolute);
        expect($info[0])->toBe($variant['width'])
            ->and($info[1])->toBe($variant['height'])
            ->and($info['mime'])->toBe('image/'.$variant['format'])
            ->and(filesize($absolute))->toBe($variant['size']);
    }

    $jpeg = Storage::disk('public')->path($variants->firstWhere('format', 'jpeg')['path']);
    $exif = @exif_read_data($jpeg, null, true);
    expect($exif === false || (! isset($exif['IFD0']['Orientation']) && ! isset($exif['GPS'])))->toBeTrue();
    expect(file_get_contents($jpeg))->not->toContain('Exif');
});

test('png images keep a png fallback', function () {
    $asset = quarantinedMedia(MediaFixtures::pngBytes(30, 30), ['mime' => 'image/png', 'path' => 'png/original.png']);
    $asset->forceFill(['status' => MediaStatus::Clean])->save();

    app()->call([new GenerateImageVariants($asset->id), 'handle']);

    expect(collect($asset->refresh()->variants)->pluck('format')->all())->toBe(['avif', 'webp', 'png'])
        ->and($asset->variants[0]['width'])->toBe(30);
});

test('variants are never generated for quarantined or rejected assets', function (string $state) {
    $asset = quarantinedMedia(MediaFixtures::jpegBytes());
    $asset->forceFill(['status' => MediaStatus::from($state)])->save();

    app()->call([new GenerateImageVariants($asset->id), 'handle']);

    expect($asset->refresh()->variants)->toBeNull()
        ->and(Storage::disk('public')->allFiles())->toBe([]);
})->with(['quarantine', 'rejected']);
