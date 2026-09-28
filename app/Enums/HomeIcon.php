<?php

namespace App\Enums;

/**
 * Icons available to home page feature items. The React side maps every
 * value to one Lucide icon (static map, exhaustive by type).
 */
enum HomeIcon: string
{
    case Palette = 'palette';
    case Lock = 'lock';
    case Zap = 'zap';
    case Accessibility = 'accessibility';
    case ShieldCheck = 'shield-check';
    case Rocket = 'rocket';
    case Sparkles = 'sparkles';
    case Globe = 'globe';
}
