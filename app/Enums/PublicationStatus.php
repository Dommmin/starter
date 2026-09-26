<?php

namespace App\Enums;

/**
 * Publication state of a single content translation.
 */
enum PublicationStatus: string
{
    case Draft = 'draft';
    case Published = 'published';
}
