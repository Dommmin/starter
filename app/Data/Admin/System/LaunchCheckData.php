<?php

namespace App\Data\Admin\System;

use App\Enums\LaunchCheck;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * One item of the launch checklist.
 */
#[TypeScript]
class LaunchCheckData extends Data
{
    public function __construct(
        public LaunchCheck $key,
        public bool $done,
    ) {}
}
