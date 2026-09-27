<?php

namespace App\Services\Media;

use App\Models\MediaAsset;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Support\Facades\Storage;
use Intervention\Image\ImageManager;
use Intervention\Image\Interfaces\EncodedImageInterface;
use Intervention\Image\Interfaces\ImageInterface;
use RuntimeException;

/**
 * Produce the public responsive variants of a clean DAM image with GD:
 * auto-oriented, re-encoded (GD writes no EXIF/XMP/ICC metadata), never
 * upscaled, as AVIF, WebP and a fallback (PNG for PNG sources, JPEG
 * otherwise). File names carry a content hash, so URLs can be cached as
 * immutable: `media/{uuid}/{hash}-{width}.{ext}`.
 */
final class ImageVariantGenerator
{
    /**
     * @return array{width: int, height: int, variants: list<array{format: string, width: int, height: int, path: string, size: int}>}
     *
     * @throws RuntimeException When the image cannot be decoded safely.
     */
    public function generate(MediaAsset $asset): array
    {
        $source = Storage::disk($asset->disk);
        $publicDisk = self::publicDisk();
        $absolutePath = $source->path($asset->path);

        $this->assertDecodable($absolutePath);

        $manager = ImageManager::gd(autoOrientation: true, decodeAnimation: false, strip: true);
        $image = $manager->read($absolutePath);
        $originalWidth = $image->width();
        $originalHeight = $image->height();

        $directory = self::directory($asset);
        $publicDisk->deleteDirectory($directory);

        $variants = [];

        foreach ($this->targetWidths($originalWidth) as $width) {
            $scaled = (clone $image)->scaleDown(width: $width);

            foreach ($this->formats($asset) as $format => $extension) {
                $encoded = $this->encode($scaled, $format);
                $bytes = $encoded->toString();
                $hash = substr(hash('sha256', $bytes), 0, 16);
                $path = "{$directory}/{$hash}-{$scaled->width()}.{$extension}";

                $publicDisk->put($path, $bytes);

                $variants[] = [
                    'format' => $format,
                    'width' => $scaled->width(),
                    'height' => $scaled->height(),
                    'path' => $path,
                    'size' => strlen($bytes),
                ];
            }
        }

        return ['width' => $originalWidth, 'height' => $originalHeight, 'variants' => $variants];
    }

    /**
     * Remove every public variant of the asset.
     */
    public function delete(MediaAsset $asset): void
    {
        self::publicDisk()->deleteDirectory(self::directory($asset));
    }

    public static function directory(MediaAsset $asset): string
    {
        return "media/{$asset->uuid}";
    }

    public static function publicDisk(): Filesystem
    {
        return Storage::disk((string) config('media.public_disk'));
    }

    /**
     * Configured widths below the source width plus the source width itself
     * (capped at the largest configured width). Never upscales.
     *
     * @return list<int>
     */
    private function targetWidths(int $originalWidth): array
    {
        /** @var non-empty-list<int> $configured */
        $configured = config('media.variant_widths');
        $largest = max($configured);

        $widths = array_filter($configured, fn (int $width): bool => $width < $originalWidth);
        $widths[] = min($originalWidth, $largest);

        $widths = array_values(array_unique($widths));
        sort($widths);

        return $widths;
    }

    /**
     * Output formats in `<picture>` source order: format => file extension.
     *
     * @return array<string, string>
     */
    private function formats(MediaAsset $asset): array
    {
        return [
            'avif' => 'avif',
            'webp' => 'webp',
            ...($asset->mime === 'image/png' ? ['png' => 'png'] : ['jpeg' => 'jpg']),
        ];
    }

    private function encode(ImageInterface $image, string $format): EncodedImageInterface
    {
        /** @var array{avif: int, webp: int, jpeg: int} $quality */
        $quality = config('media.quality');

        return match ($format) {
            'avif' => $image->toAvif(quality: $quality['avif'], strip: true),
            'webp' => $image->toWebp(quality: $quality['webp'], strip: true),
            'png' => $image->toPng(),
            default => $image->toJpeg(quality: $quality['jpeg'], progressive: true, strip: true),
        };
    }

    /**
     * Guard against decompression bombs before GD allocates the bitmap.
     */
    private function assertDecodable(string $absolutePath): void
    {
        $info = @getimagesize($absolutePath);

        if ($info === false || $info[0] < 1 || $info[1] < 1) {
            throw new RuntimeException('The image header cannot be read.');
        }

        if ((int) config('media.max_pixels') < $info[0] * $info[1]) {
            throw new RuntimeException('The image has too many pixels.');
        }
    }
}
