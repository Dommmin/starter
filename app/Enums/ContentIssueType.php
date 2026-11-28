<?php

namespace App\Enums;

use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
enum ContentIssueType: string
{
    case BrokenLink = 'broken_link';
    case EmptyContent = 'empty_content';
    case MissingDescription = 'missing_description';
    case DuplicateTitle = 'duplicate_title';
    case MissingAltText = 'missing_alt_text';
}
