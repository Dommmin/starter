<?php

namespace App\Data\Admin\Media;

use App\Data\Media\MediaImageData;
use App\Models\MediaAsset;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * Clean image offered by the rich text image picker.
 */
#[TypeScript]
class MediaPickerItemData extends Data
{
    public function __construct(
        public int $id,
        public string $name,
        public ?string $alt,
        public string $thumbnailUrl,
        public int $width,
        public int $height,
    ) {}

    /**
     * Null for assets without usable variants.
     */
    public static function fromModel(MediaAsset $asset): ?self
    {
        $image = MediaImageData::fromAsset($asset);
        $thumbnailUrl = MediaImageData::thumbnailUrl($asset);

        if ($image === null || $thumbnailUrl === null) {
            return null;
        }

        return new self(
            id: $asset->id,
            name: $asset->original_name,
            alt: $asset->alt,
            thumbnailUrl: $thumbnailUrl,
            width: $image->width,
            height: $image->height,
        );
    }
}
