<?php

namespace App\Data\Admin\Media;

use App\Services\Media\ImageVariantGenerator;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * One generated public variant, listed on the asset details screen.
 */
#[TypeScript]
class MediaVariantData extends Data
{
    public function __construct(
        public string $format,
        public int $width,
        public int $height,
        public int $size,
        public string $url,
    ) {}

    /**
     * @param  array{format: string, width: int, height: int, path: string, size: int}  $variant
     */
    public static function fromArray(array $variant): self
    {
        return new self(
            format: $variant['format'],
            width: $variant['width'],
            height: $variant['height'],
            size: $variant['size'],
            url: ImageVariantGenerator::publicDisk()->url($variant['path']),
        );
    }
}
