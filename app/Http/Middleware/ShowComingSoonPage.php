<?php

namespace App\Http\Middleware;

use App\Data\Content\ComingSoonPageData;
use App\Repositories\Settings\SiteSettingsRepository;
use App\Services\Localization\LocalizationManager;
use Closure;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\Response;

/**
 * While the coming soon switch is on, guests and users without panel
 * access get the coming soon page (200, noindex) instead of public pages.
 * Panel users see the whole site; sign-in, panel, account, health, robots,
 * sitemap and non-GET requests are never replaced. Runs after
 * HandleInertiaRequests so the page has the shared props.
 */
class ShowComingSoonPage
{
    /** Route names that stay reachable for guests. */
    private const array ALWAYS_AVAILABLE = ['robots', 'sitemap', 'health.ready', 'dev.login'];

    public function __construct(
        private readonly SiteSettingsRepository $siteSettings,
        private readonly LocalizationManager $localization,
    ) {}

    /**
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (! $this->replaces($request)) {
            return $next($request);
        }

        return Inertia::render('public/coming-soon', new ComingSoonPageData(
            contactEmail: $this->siteSettings->current()->contact->email,
        ))->toResponse($request);
    }

    private function replaces(Request $request): bool
    {
        if (! $this->siteSettings->isComingSoon() || ! $request->isMethodSafe()) {
            return false;
        }

        if ($request->user()?->canAccessAdminPanel() ?? false) {
            return false;
        }

        $area = $request->attributes->get('localization.area') ?? $this->localization->determineArea($request);

        return $area === 'public'
            && ! $this->localization->isAuthPath($request)
            && ! in_array($request->route()?->getName(), self::ALWAYS_AVAILABLE, true);
    }
}
