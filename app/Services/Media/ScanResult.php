<?php

namespace App\Services\Media;

/**
 * Definitive verdict of a malware scan, or the explicit release of an upload
 * while the scanner is disabled (`scanned` is false).
 */
final readonly class ScanResult
{
    private function __construct(
        public bool $clean,
        public ?string $signature,
        public bool $scanned = true,
    ) {}

    public static function clean(): self
    {
        return new self(true, null);
    }

    public static function infected(string $signature): self
    {
        return new self(false, $signature);
    }

    public static function skipped(): self
    {
        return new self(true, null, false);
    }
}
