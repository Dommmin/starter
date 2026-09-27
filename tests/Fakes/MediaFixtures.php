<?php

namespace Tests\Fakes;

use Illuminate\Http\UploadedFile;
use RuntimeException;

/**
 * Small synthetic files generated in memory with GD, including a JPEG with
 * an EXIF Orientation tag and GPS data to prove metadata stripping.
 */
final class MediaFixtures
{
    public static function jpegBytes(int $width = 40, int $height = 20, bool $withExif = false): string
    {
        $image = imagecreatetruecolor($width, $height);

        if ($image === false) {
            throw new RuntimeException('GD is required for media fixtures.');
        }

        imagefill($image, 0, 0, (int) imagecolorallocate($image, 200, 30, 30));
        ob_start();
        imagejpeg($image, null, 90);
        $bytes = (string) ob_get_clean();

        return $withExif ? self::injectExif($bytes) : $bytes;
    }

    public static function pngBytes(int $width = 30, int $height = 30): string
    {
        $image = imagecreatetruecolor($width, $height);

        if ($image === false) {
            throw new RuntimeException('GD is required for media fixtures.');
        }

        ob_start();
        imagepng($image);

        return (string) ob_get_clean();
    }

    public static function upload(string $name, string $bytes): UploadedFile
    {
        $path = tempnam(sys_get_temp_dir(), 'media-test-');

        if ($path === false) {
            throw new RuntimeException('Cannot create a temporary file.');
        }

        file_put_contents($path, $bytes);

        return new UploadedFile($path, $name, null, null, true);
    }

    public static function pdfBytes(): string
    {
        return "%PDF-1.4\n1 0 obj << /Type /Catalog >> endobj\ntrailer << /Root 1 0 R >>\n%%EOF\n";
    }

    /**
     * APP1/EXIF segment (big-endian TIFF) with Orientation = 6 (rotate 90°
     * clockwise) and a GPS IFD holding GPSLatitudeRef = N.
     */
    private static function injectExif(string $jpeg): string
    {
        $ifd0 = pack('n', 2)
            .pack('nnN', 0x0112, 3, 1).pack('nn', 6, 0)
            .pack('nnNN', 0x8825, 4, 1, 38)
            .pack('N', 0);
        $gps = pack('n', 1)
            .pack('nnN', 0x0001, 2, 2)."N\0\0\0"
            .pack('N', 0);
        $tiff = 'MM'.pack('nN', 0x2A, 8).$ifd0.$gps;
        $payload = "Exif\0\0".$tiff;
        $segment = "\xFF\xE1".pack('n', strlen($payload) + 2).$payload;

        return substr($jpeg, 0, 2).$segment.substr($jpeg, 2);
    }
}
