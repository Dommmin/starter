<?php

namespace App\Data\Settings;

use App\Data\Media\MediaImageData;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * Shared `site` Inertia prop: public brand, contact and social data in the
 * current locale. Never contains the contact form recipient.
 *
 * `logo` is a clean DAM image with variants (null otherwise); `tagline` and
 * `footerText` fall back to the public fallback locale and are null when no
 * locale provides them.
 */
#[TypeScript]
class SiteSettingsData extends Data
{
    /**
     * @param  list<SiteSocialLinkData>  $social
     */
    public function __construct(
        public string $name,
        public ?MediaImageData $logo,
        public ?string $tagline,
        public ?string $footerText,
        public SiteContactData $contact,
        public array $social,
    ) {}
}
