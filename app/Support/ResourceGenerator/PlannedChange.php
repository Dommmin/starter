<?php

namespace App\Support\ResourceGenerator;

/**
 * One file the generator will create (`original` null) or extend.
 */
final readonly class PlannedChange
{
    public function __construct(
        public string $path,
        public string $contents,
        public ?string $original,
    ) {}

    public function isNew(): bool
    {
        return $this->original === null;
    }
}
