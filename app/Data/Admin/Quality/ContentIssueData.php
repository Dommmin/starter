<?php

namespace App\Data\Admin\Quality;

use App\Enums\ContentIssueType;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
class ContentIssueData extends Data
{
    /**
     * @param  string|null  $detail  The broken address, for broken links.
     */
    public function __construct(
        public string $id,
        public ContentIssueType $type,
        public string $label,
        public ?string $locale,
        public string $editUrl,
        public ?string $detail = null,
    ) {}
}
