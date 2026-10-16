<?php

namespace App\Enums;

/**
 * Colour theme: the visitor's `appearance` cookie, or the site default
 * chosen in the settings when the visitor has not picked one.
 */
enum ThemePreference: string
{
    case Light = 'light';
    case Dark = 'dark';
    case System = 'system';
}
