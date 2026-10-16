<?php

namespace App\Enums;

/**
 * Typography preset of the public site (rendered as `data-typography` on
 * <html>). Each preset sets `--font-heading` in resources/css/app.css; the
 * fonts of a preset load only when its faces are used. A new case is a
 * design system decision.
 */
enum TypographyPreset: string
{
    /** Instrument Sans for headings and text. */
    case Default = 'default';

    /** Fraunces (serif) headings with Instrument Sans text. */
    case Classic = 'classic';
}
