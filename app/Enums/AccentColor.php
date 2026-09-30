<?php

namespace App\Enums;

/**
 * Accent colour of a new project. Only the design system default exists;
 * further variants require a design system decision (new tokens).
 */
enum AccentColor: string
{
    case Default = 'default';
}
