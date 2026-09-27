<?php

namespace App\Data\Admin\Media;

use App\Data\Media\MediaImageData;
use App\Enums\MediaStatus;
use App\Models\MediaAsset;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * One row of the admin media list. `thumbnailUrl` exists only for clean
 * images with variants; quarantined or rejected files have no URL at all.
 */
#[TypeScript]
class MediaAssetListItemData extends Data
{
    public function __construct(
        public int $id,
        public string $originalName,
        public string $mime,
        public bool $isImage,
        public int $size,
        public ?int $width,
        public ?int $height,
        public MediaStatus $status,
        public bool $hasScanError,
        public ?string $alt,
        public ?string $thumbnailUrl,
        public ?string $createdAt,
    ) {}

    public static function fromModel(MediaAsset $asset): self
    {
        return new self(
            id: $asset->id,
            originalName: $asset->original_name,
            mime: $asset->mime,
            isImage: $asset->isImage(),
            size: $asset->size,
            width: $asset->width,
            height: $asset->height,
            status: $asset->status,
            hasScanError: $asset->scan_error !== null,
            alt: $asset->alt,
            thumbnailUrl: MediaImageData::thumbnailUrl($asset),
            createdAt: $asset->created_at?->toIso8601String(),
        );
    }
}
