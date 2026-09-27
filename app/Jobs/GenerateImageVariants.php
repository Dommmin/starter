<?php

namespace App\Jobs;

use App\Models\MediaAsset;
use App\Services\Media\ImageVariantGenerator;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\DB;

/**
 * Generate the public AVIF/WebP/fallback variants of a clean image.
 * Idempotent: the variant directory is rebuilt from scratch, and assets
 * that are no longer clean images are skipped.
 */
class GenerateImageVariants implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    /**
     * Below the Redis `retry_after` (90 s) of the default queue connection.
     */
    public int $timeout = 80;

    public int $uniqueFor = 3600;

    public function __construct(public readonly int $mediaAssetId) {}

    public function uniqueId(): string
    {
        return (string) $this->mediaAssetId;
    }

    /**
     * @return list<int>
     */
    public function backoff(): array
    {
        return [60, 300];
    }

    public function handle(ImageVariantGenerator $generator): void
    {
        $asset = MediaAsset::query()->find($this->mediaAssetId);

        if ($asset === null || ! $asset->hasPassedScan() || ! $asset->isImage()) {
            return;
        }

        $result = $generator->generate($asset);

        DB::transaction(function () use ($asset, $result): void {
            $locked = MediaAsset::query()->whereKey($asset->id)->lockForUpdate()->first();

            if ($locked === null || ! $locked->hasPassedScan()) {
                return;
            }

            $locked->forceFill([
                'width' => $result['width'],
                'height' => $result['height'],
                'variants' => $result['variants'],
            ])->save();
        });
    }
}
