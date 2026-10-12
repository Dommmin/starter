<?php

namespace App\Enums;

/**
 * Accent colour of a project (`APP_ACCENT`, rendered as `data-accent` on
 * <html>). Every preset needs light and dark values on both surfaces in
 * resources/css/app.css and AA contrast, which tests/Unit/AccentContrastTest
 * measures; a new case is a design system decision.
 */
enum AccentColor: string
{
    case Default = 'default';
    case Blue = 'blue';
    case Violet = 'violet';
    case Rose = 'rose';
    case Green = 'green';
}
