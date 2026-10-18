<?php

namespace App\Data\Admin\Import;

use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * Result of checking an uploaded CSV. Only a file without any error gets a
 * `token`, which imports exactly the checked rows within 15 minutes.
 */
#[TypeScript]
class ImportPreviewData extends Data
{
    /**
     * @param  list<string>  $fileErrors  Problems of the whole file (encoding, columns, size).
     * @param  list<ImportRowData>  $rows  Every invalid row, then the first valid ones.
     */
    public function __construct(
        public string $fileName,
        public array $fileErrors,
        public array $rows,
        public int $total,
        public int $invalid,
        public ?string $token,
    ) {}
}
