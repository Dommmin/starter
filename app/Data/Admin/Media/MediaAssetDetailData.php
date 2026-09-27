<?php

namespace App\Data\Admin\Media;

use App\Data\Media\MediaImageData;
use App\Enums\MediaStatus;
use App\Models\MediaAsset;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * Asset details and editable metadata. `updatedAt` must be sent back on
 * update for optimistic locking. `downloadUrl` exists only for clean assets.
 */
#[TypeScript]
class MediaAssetDetailData extends Data
{
    /**
     * @param  list<MediaVariantData>  $variants
     */
    public function __construct(
        public int $id,
        public ?string $updatedAt,
        public string $originalName,
        public string $mime,
        public bool $isImage,
        public int $size,
        public ?int $width,
        public ?int $height,
        public string $checksum,
        public MediaStatus $status,
        public ?string $scanError,
        public ?string $alt,
        public ?string $ownerName,
        public ?string $createdAt,
        public ?MediaImageData $image,
        public array $variants,
        public ?string $downloadUrl,
    ) {}

    /**
     * Requires the `owner` relation to be eager loaded.
     */
    public static function fromModel(MediaAsset $asset): self
    {
        return new self(
            id: $asset->id,
            updatedAt: $asset->updated_at?->toIso8601String(),
            originalName: $asset->original_name,
            mime: $asset->mime,
            isImage: $asset->isImage(),
            size: $asset->size,
            width: $asset->width,
            height: $asset->height,
            checksum: $asset->checksum,
            status: $asset->status,
            scanError: $asset->scan_error,
            alt: $asset->alt,
            ownerName: $asset->owner?->name,
            createdAt: $asset->created_at?->toIso8601String(),
            image: MediaImageData::fromAsset($asset),
            variants: $asset->hasPassedScan()
                ? array_map(MediaVariantData::fromArray(...), $asset->variants ?? [])
                : [],
            downloadUrl: $asset->hasPassedScan() ? route('admin.media.download', $asset) : null,
        );
    }
}
