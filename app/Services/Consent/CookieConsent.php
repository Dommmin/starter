<?php

namespace App\Services\Consent;

use App\Data\Consent\ConsentData;
use App\Data\Consent\PlausibleData;
use App\Enums\ConsentCategory;
use App\Models\PageTranslation;
use App\Repositories\Settings\SiteSettingsRepository;
use App\Services\Localization\LocalizedUrlGenerator;
use Illuminate\Http\Request;

/**
 * Cookie consent of public pages (config/consent.php). The browser writes
 * the choice as JSON `{"v": version, "o": offered, "g": granted}` to an
 * unencrypted cookie; a choice counts only for the current version and
 * while it covered every category offered now, so a newly configured
 * service asks the visitor again.
 */
class CookieConsent
{
    /** Excluded from cookie encryption in bootstrap/app.php. */
    public const string COOKIE = 'cookie_consent';

    public function __construct(
        private readonly LocalizedUrlGenerator $urls,
        private readonly SiteSettingsRepository $settings,
    ) {}

    public function forRequest(Request $request, string $locale): ConsentData
    {
        $categories = $this->offeredCategories();

        return new ConsentData(
            version: $this->version(),
            cookieName: self::COOKIE,
            lifetimeDays: (int) config('consent.lifetime_days', 365),
            categories: $categories,
            granted: $this->granted($request->cookie(self::COOKIE), $categories),
            policyUrl: $this->policyUrl($locale),
            plausible: $this->plausible(),
        );
    }

    /**
     * Optional categories with at least one configured service.
     *
     * @return list<ConsentCategory>
     */
    public function offeredCategories(): array
    {
        $categories = [];

        foreach ((array) config('consent.categories', []) as $value => $services) {
            $category = ConsentCategory::tryFrom((string) $value);

            if ($category !== null && array_filter((array) $services, $this->isConfigured(...)) !== []) {
                $categories[] = $category;
            }
        }

        return $categories;
    }

    /**
     * Categories the stored choice allows, or null when there is no valid
     * choice for the offered categories.
     *
     * @param  list<ConsentCategory>  $offered
     * @return list<ConsentCategory>|null
     */
    private function granted(mixed $cookie, array $offered): ?array
    {
        if (! is_string($cookie) || strlen($cookie) > 512) {
            return null;
        }

        $choice = json_decode($cookie, true);

        if (! is_array($choice) || ($choice['v'] ?? null) !== $this->version() || ! is_array($choice['o'] ?? null) || ! is_array($choice['g'] ?? null)) {
            return null;
        }

        foreach ($offered as $category) {
            if (! in_array($category->value, $choice['o'], true)) {
                return null;
            }
        }

        return array_values(array_filter($offered, fn (ConsentCategory $category): bool => in_array($category->value, $choice['g'], true)));
    }

    private function version(): int
    {
        return (int) config('consent.version', 1);
    }

    private function isConfigured(mixed $service): bool
    {
        return match ($service) {
            'plausible' => $this->plausible() !== null,
            default => false,
        };
    }

    private function plausible(): ?PlausibleData
    {
        return $this->settings->plausible();
    }

    /**
     * URL of the first published policy page of the locale.
     */
    private function policyUrl(string $locale): ?string
    {
        $slugs = array_values(array_filter((array) config("consent.policy_slugs.{$locale}", []), 'is_string'));

        if ($slugs === []) {
            return null;
        }

        $published = PageTranslation::query()
            ->published()
            ->where('locale', $locale)
            ->whereIn('slug', $slugs)
            ->pluck('slug')
            ->all();

        foreach ($slugs as $slug) {
            if (in_array($slug, $published, true)) {
                return $this->urls->url('pages.show', ['slug' => $slug], $locale);
            }
        }

        return null;
    }
}
