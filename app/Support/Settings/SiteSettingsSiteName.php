<?php

namespace App\Support\Settings;

use App\Actions\Settings\UpdateSiteSettings;
use App\Contracts\Settings\UpdatesSiteName;
use App\Repositories\Settings\SiteSettingsRepository;

/**
 * Site name of `app:init-project`, stored in the site settings singleton
 * through the audited {@see UpdateSiteSettings::updateSiteName()} action
 * (console actor = null).
 */
class SiteSettingsSiteName implements UpdatesSiteName
{
    public function __construct(
        private readonly UpdateSiteSettings $updateSiteSettings,
        private readonly SiteSettingsRepository $settings,
    ) {}

    public function isAvailable(): bool
    {
        return true;
    }

    /**
     * The saved name; null until the settings were saved for the first time
     * (the `seo.site_name` fallback is not a stored value).
     */
    public function currentSiteName(): ?string
    {
        return $this->settings->exists() ? $this->settings->siteName() : null;
    }

    public function updateSiteName(string $name): bool
    {
        if ($this->currentSiteName() === trim($name)) {
            return false;
        }

        $this->updateSiteSettings->updateSiteName($name, null);

        return true;
    }
}
