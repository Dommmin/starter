<?php

namespace App\Enums;

/**
 * Visibility of a previewed translation for visitors: never published, published
 * with a future (or missing) publication date, or already visible.
 */
enum ContentPreviewState: string
{
    case Draft = 'draft';
    case Scheduled = 'scheduled';
    case Published = 'published';
}
