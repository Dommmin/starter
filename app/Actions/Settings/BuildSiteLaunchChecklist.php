<?php

namespace App\Actions\Settings;

use App\Data\Admin\System\LaunchCheckData;
use App\Data\Admin\System\SiteLaunchData;
use App\Enums\LaunchCheck;
use App\Enums\PublicationStatus;
use App\Enums\UserRole;
use App\Models\PageTranslation;
use App\Models\User;
use App\Repositories\Settings\SiteSettingsRepository;
use App\Services\Localization\LocalizationConfig;
use App\Support\Content\LegalPages;
use Illuminate\Support\Facades\Cache;

/**
 * Publication switches and launch checklist of the System page.
 */
class BuildSiteLaunchChecklist
{
    /** Cache key with the time of the last test e-mail (ISO 8601). */
    public const string TEST_MAIL_SENT_KEY = 'site_launch:test_mail_sent_at';

    public function __construct(
        private readonly SiteSettingsRepository $settings,
        private readonly LocalizationConfig $localization,
    ) {}

    public function handle(User $viewer): SiteLaunchData
    {
        $appearance = $this->settings->appearance();

        return new SiteLaunchData(
            comingSoon: $this->settings->isComingSoon(),
            noindex: $this->settings->isNoindexSwitchOn(),
            maintenance: $this->settings->isMaintenance(),
            maintenanceUntil: $this->settings->maintenanceUntil()?->toIso8601String(),
            checklist: [
                new LaunchCheckData(LaunchCheck::AdminTwoFactor, $this->adminsHaveTwoFactor()),
                new LaunchCheckData(LaunchCheck::TestMail, Cache::has(self::TEST_MAIL_SENT_KEY)),
                new LaunchCheckData(LaunchCheck::SearchConsole, $appearance->googleSiteVerification !== null),
                new LaunchCheckData(LaunchCheck::Analytics, $this->settings->plausible() !== null),
                new LaunchCheckData(LaunchCheck::LegalPages, $this->legalPagesPublished()),
            ],
            testMailRecipient: $viewer->email,
        );
    }

    private function adminsHaveTwoFactor(): bool
    {
        $admins = User::query()->where('role', UserRole::Admin->value);

        return $admins->exists()
            && ! (clone $admins)->whereNull('two_factor_confirmed_at')->exists();
    }

    /**
     * Every legal page has a published translation in the default public
     * language.
     */
    private function legalPagesPublished(): bool
    {
        $locale = $this->localization->getPublicDefault();

        foreach (LegalPages::SLUGS as $slugs) {
            $slug = $slugs[$locale] ?? null;

            $published = $slug !== null && PageTranslation::query()
                ->where('locale', $locale)
                ->where('slug', $slug)
                ->where('status', PublicationStatus::Published->value)
                ->where('published_at', '<=', now())
                ->exists();

            if (! $published) {
                return false;
            }
        }

        return true;
    }
}
