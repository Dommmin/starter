<?php

namespace App\Enums;

/**
 * Optional cookie categories a visitor may allow in the banner. Necessary
 * cookies need no consent and have no case here.
 */
enum ConsentCategory: string
{
    case Analytics = 'analytics';
    case Marketing = 'marketing';
}
