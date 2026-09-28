<?php

namespace App\Support\Settings;

use App\Contracts\Settings\UpdatesSiteName;

/**
 * Default binding while the application settings module is not installed:
 * nothing is stored and the init step is reported as skipped.
 */
class SkipSiteNameUpdate implements UpdatesSiteName
{
    public function isAvailable(): bool
    {
        return false;
    }

    public function currentSiteName(): ?string
    {
        return null;
    }

    public function updateSiteName(string $name): bool
    {
        return false;
    }
}
