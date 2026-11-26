<?php

namespace App\Services\Media;

use Intervention\Image\Drivers\Gd\Driver as GdDriver;
use Intervention\Image\Encoders\AvifEncoder;
use Intervention\Image\Encoders\JpegEncoder;
use Intervention\Image\Encoders\PngEncoder;
use Intervention\Image\Encoders\WebpEncoder;
use Intervention\Image\ImageManager;
use Throwable;

/**
 * Which formats GD on this host can actually write. gd_info() is not
 * enough: Ubuntu 24.04 builds libgd without AVIF, so PHP reports AVIF
 * support while imageavif() only warns and writes nothing. Each format is
 * probed once per process by encoding a 1×1 image.
 */
class ImageFormatSupport
{
    /**
     * @var array<string, bool>
     */
    private array $probed = [];

    public function encodes(string $format): bool
    {
        return $this->probed[$format] ??= $this->probe($format);
    }

    private function probe(string $format): bool
    {
        $encoder = match ($format) {
            'avif' => new AvifEncoder,
            'webp' => new WebpEncoder,
            'png' => new PngEncoder,
            'jpeg' => new JpegEncoder,
            default => null,
        };

        if ($encoder === null) {
            return false;
        }

        // GD reports a missing codec as a warning, not as a failure.
        set_error_handler(fn (): bool => throw new \ErrorException('GD warning'));

        try {
            $image = ImageManager::usingDriver(GdDriver::class)->createImage(1, 1);

            return $image->encode($encoder)->toString() !== '';
        } catch (Throwable) {
            return false;
        } finally {
            restore_error_handler();
        }
    }
}
