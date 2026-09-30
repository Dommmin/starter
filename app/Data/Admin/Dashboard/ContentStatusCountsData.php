<?php

namespace App\Data\Admin\Dashboard;

use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * Publication state counts of one content type, measured on the translation
 * in the default public language (every record has one).
 */
#[TypeScript]
class ContentStatusCountsData extends Data
{
    /**
     * @param  int  $published  Visible to visitors now.
     * @param  int  $scheduled  Marked as published with a future publication date.
     */
    public function __construct(
        public int $published,
        public int $drafts,
        public int $scheduled,
    ) {}
}
