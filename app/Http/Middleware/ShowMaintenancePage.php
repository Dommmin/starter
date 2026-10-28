<?php

namespace App\Http\Middleware;

use App\Data\Content\MaintenancePageData;
use App\Repositories\Settings\SiteSettingsRepository;
use App\Services\Localization\LocalizationManager;
use Closure;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\Response;

/**
 * While the maintenance switch is on, guests and users without panel access
 * get the maintenance page (503 with `Retry-After`) for every public
 * request, form submissions included, so search engines keep the indexed
 * pages. Panel users see the whole site; sign-in, panel, account, health,
 * robots and sitemap are never replaced. Runs before ShowComingSoonPage,
 * so maintenance wins when both switches are on.
 */
class ShowMaintenancePage
{
    /** Route names that stay reachable for guests. */
    private const array ALWAYS_AVAILABLE = ['robots', 'sitemap', 'health.ready', 'dev.login'];

    /** `Retry-After` in seconds when the planned end is unknown or has passed. */
    public const int DEFAULT_RETRY_AFTER = 3600;

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

        $until = $this->siteSettings->maintenanceUntil();
        $secondsLeft = $until === null ? 0 : (int) ceil(now()->diffInSeconds($until, false));

        $response = Inertia::render('public/maintenance', new MaintenancePageData(
            contactEmail: $this->siteSettings->current()->contact->email,
            minutesLeft: $secondsLeft > 0 ? (int) ceil($secondsLeft / 60) : null,
        ))->toResponse($request);

        $response->setStatusCode(Response::HTTP_SERVICE_UNAVAILABLE);
        $response->headers->set('Retry-After', (string) ($secondsLeft > 0 ? $secondsLeft : self::DEFAULT_RETRY_AFTER));

        return $response;
    }

    private function replaces(Request $request): bool
    {
        if (! $this->siteSettings->isMaintenance()) {
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
