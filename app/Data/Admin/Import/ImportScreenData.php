<?php

namespace App\Data\Admin\Import;

use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\LiteralTypeScriptType;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * Props of the `admin/import/show` screen.
 */
#[TypeScript]
class ImportScreenData extends Data
{
    /**
     * @param  list<string>  $columns  Accepted header names in order.
     * @param  list<string>  $requiredColumns
     */
    public function __construct(
        #[LiteralTypeScriptType("'faqs' | 'redirects' | 'tags'")]
        public string $module,
        public array $columns,
        public array $requiredColumns,
        public int $maxRows,
        public int $maxKilobytes,
        public ?ImportPreviewData $preview = null,
    ) {}
}
