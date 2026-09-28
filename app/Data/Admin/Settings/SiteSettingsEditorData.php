<?php

namespace App\Data\Admin\Settings;

use App\Data\Content\ContentLocalesData;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * Props of the `admin/site-settings/edit` screen.
 */
#[TypeScript]
class SiteSettingsEditorData extends Data
{
    /**
     * @param  list<SocialNetworkOptionData>  $socialNetworks
     */
    public function __construct(
        public SiteSettingsFormData $settings,
        public ContentLocalesData $locales,
        public array $socialNetworks,
    ) {}
}
