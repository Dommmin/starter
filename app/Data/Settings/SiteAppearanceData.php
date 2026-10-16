<?php

namespace App\Data\Settings;

use App\Enums\AccentColor;
use App\Enums\ThemePreference;
use App\Enums\TypographyPreset;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\Hidden;

/**
 * Document-level appearance rendered by the Blade root view: accent and
 * typography presets (`data-accent`, `data-typography`), the theme used
 * before the visitor picks one, the favicon from the media library and the
 * Search Console verification token.
 */
#[Hidden]
class SiteAppearanceData extends Data
{
    public function __construct(
        public AccentColor $accent,
        public TypographyPreset $typography,
        public ThemePreference $defaultTheme,
        public ?string $faviconUrl,
        public ?string $faviconType,
        public ?string $googleSiteVerification,
    ) {}
}
