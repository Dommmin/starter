<?php

namespace App\Data\Home;

use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * One figure of the `hero` section, e.g. "300+" with "projects delivered".
 * The value is display text, not a number, so it may carry a suffix.
 */
#[TypeScript]
class HeroStatData extends Data
{
    public function __construct(
        public string $value,
        public string $label,
    ) {}
}
