<?php

namespace App\Data\Admin\Import;

use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * One CSV row of the import preview with its validation messages.
 */
#[TypeScript]
class ImportRowData extends Data
{
    /**
     * @param  int  $line  Line in the file (the header is line 1).
     * @param  list<string>  $errors
     */
    public function __construct(
        public int $line,
        public string $label,
        public array $errors,
    ) {}
}
