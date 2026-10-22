<?php

namespace App\Enums;

/**
 * Lifecycle of an uploaded DAM file: every upload starts in quarantine and
 * becomes usable only after a successful malware scan (or, with the scanner
 * disabled, after the scan job releases it as clean).
 */
enum MediaStatus: string
{
    case Quarantine = 'quarantine';
    case Clean = 'clean';
    case Rejected = 'rejected';
}
