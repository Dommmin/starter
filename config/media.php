<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Storage
    |--------------------------------------------------------------------------
    |
    | Originals (every status) live on the private `media` disk and are served
    | only through the authorized download route. Image variants of clean
    | assets are written to the public disk under content-hashed names.
    | Both disks live in `storage/`, which Deployer shares between releases
    | and which is covered by the storage backup.
    |
    */

    'disk' => env('MEDIA_DISK', 'media'),

    'public_disk' => env('MEDIA_PUBLIC_DISK', 'public'),

    /*
    |--------------------------------------------------------------------------
    | Upload limits and allowlist
    |--------------------------------------------------------------------------
    |
    | The size limit must stay in sync with Nginx `client_max_body_size` and
    | PHP `upload_max_filesize`/`post_max_size`. The MIME type is detected
    | from the file content (fileinfo); the client extension must match it.
    | Map: detected MIME type => allowed client extensions (first = stored).
    |
    */

    'max_upload_kb' => (int) env('MEDIA_MAX_UPLOAD_KB', 51_200),

    'allowed_types' => [
        'image/jpeg' => ['jpg', 'jpeg'],
        'image/png' => ['png'],
        'image/webp' => ['webp'],
        'image/avif' => ['avif'],
        'application/pdf' => ['pdf'],
    ],

    /*
    |--------------------------------------------------------------------------
    | Image variants
    |--------------------------------------------------------------------------
    |
    | Only clean images get variants: auto-oriented, re-encoded without any
    | EXIF/metadata, never upscaled. Every width is written as AVIF and WebP
    | plus a fallback in the original family (PNG for PNG, JPEG otherwise).
    | `max_pixels` rejects decompression bombs before decoding.
    |
    */

    'variant_widths' => [320, 640, 960, 1280, 1920],

    'max_pixels' => (int) env('MEDIA_MAX_PIXELS', 40_000_000),

    'quality' => [
        'avif' => 60,
        'webp' => 80,
        'jpeg' => 82,
    ],

    /*
    |--------------------------------------------------------------------------
    | Malware scanner (clamd over TCP, INSTREAM)
    |--------------------------------------------------------------------------
    */

    'scanner' => [
        'host' => env('CLAMAV_HOST', '127.0.0.1'),
        'port' => (int) env('CLAMAV_PORT', 3310),
        'timeout' => (int) env('CLAMAV_TIMEOUT', 30),
        'chunk_bytes' => 65_536,
    ],

];
