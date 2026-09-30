<?php

namespace App\Contracts\Settings;

use App\Support\Settings\SiteSettingsSiteName;
use Illuminate\Container\Attributes\Bind;

/**
 * Write access to the site name kept in the application settings, used by
 * `app:init-project`. The settings module owns the data and is bound by
 * {@see SiteSettingsSiteName}. `APP_NAME` is intentionally never written.
 */
#[Bind(SiteSettingsSiteName::class)]
interface UpdatesSiteName
{
    /**
     * Whether a settings store is available to receive the site name.
     */
    public function isAvailable(): bool;

    /**
     * The currently stored site name, or null when none is stored.
     */
    public function currentSiteName(): ?string;

    /**
     * Store the site name.
     *
     * @return bool True when the stored value changed, false when it was already set.
     */
    public function updateSiteName(string $name): bool;
}
