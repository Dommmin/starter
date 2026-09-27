<?php

namespace App\Data\Media;

use App\Models\MediaAsset;
use App\Services\Media\ImageVariantGenerator;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * Responsive picture of a clean DAM image: modern `sources` (AVIF, WebP),
 * a fallback `src`/`srcset` and the intrinsic size (prevents layout shift).
 * Alternative text is supplied separately by the caller.
 */
#[TypeScript]
class MediaImageData extends Data
{
    /**
     * @param  list<MediaImageSourceData>  $sources
     */
    public function __construct(
        public array $sources,
        public string $src,
        public string $srcset,
        public int $width,
        public int $height,
    ) {}

    /**
     * Null unless the asset is a clean image with generated variants.
     */
    public static function fromAsset(MediaAsset $asset): ?self
    {
        $variants = $asset->variants ?? [];

        if (! $asset->hasPassedScan() || ! $asset->isImage() || $variants === []) {
            return null;
        }

        $disk = ImageVariantGenerator::publicDisk();
        $byFormat = [];

        foreach ($variants as $variant) {
            $byFormat[$variant['format']][] = $variant;
        }

        $fallbackFormat = isset($byFormat['png']) ? 'png' : 'jpeg';
        $fallback = $byFormat[$fallbackFormat] ?? [];

        if ($fallback === []) {
            return null;
        }

        usort($fallback, fn (array $a, array $b): int => $a['width'] <=> $b['width']);
        $largest = $fallback[array_key_last($fallback)];

        $srcset = fn (array $items): string => implode(', ', array_map(
            fn (array $variant): string => $disk->url($variant['path']).' '.$variant['width'].'w',
            $items,
        ));

        $sources = [];
        foreach (['avif' => 'image/avif', 'webp' => 'image/webp'] as $format => $type) {
            if (isset($byFormat[$format])) {
                $items = $byFormat[$format];
                usort($items, fn (array $a, array $b): int => $a['width'] <=> $b['width']);
                $sources[] = new MediaImageSourceData($type, $srcset($items));
            }
        }

        return new self(
            sources: $sources,
            src: $disk->url($largest['path']),
            srcset: $srcset($fallback),
            width: $largest['width'],
            height: $largest['height'],
        );
    }

    /**
     * URL of the smallest fallback variant (thumbnails and editor previews).
     */
    public static function thumbnailUrl(MediaAsset $asset): ?string
    {
        $variants = array_values(array_filter(
            $asset->variants ?? [],
            fn (array $variant): bool => in_array($variant['format'], ['jpeg', 'png'], true),
        ));

        if (! $asset->hasPassedScan() || $variants === []) {
            return null;
        }

        usort($variants, fn (array $a, array $b): int => $a['width'] <=> $b['width']);

        return ImageVariantGenerator::publicDisk()->url($variants[0]['path']);
    }
}
