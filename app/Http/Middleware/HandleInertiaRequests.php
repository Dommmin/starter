<?php

namespace App\Http\Middleware;

use App\Data\Navigation\NavigationData;
use App\Data\Seo\SeoDefaultsData;
use App\Data\Seo\SeoOrganizationData;
use App\Models\AuditLog;
use App\Models\SiteSetting;
use App\Models\User;
use App\Repositories\Settings\SiteSettingsRepository;
use App\Services\Localization\LocalizationManager;
use App\Services\Localization\LocalizedUrlGenerator;
use App\Services\Navigation\PublicNavigation;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Inertia\Middleware;
use Laravel\Fortify\Features;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that's loaded on the first page visit.
     *
     * @see https://inertiajs.com/server-side-setup#root-template
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Public routes whose parameters differ per locale (translated slugs).
     *
     * @var list<string>
     */
    private const TRANSLATED_PARAMETER_ROUTES = ['pages.show', 'articles.show'];

    public function __construct(
        protected LocalizationManager $localization,
        protected LocalizedUrlGenerator $urlGenerator,
        protected SiteSettingsRepository $siteSettings,
        protected PublicNavigation $navigation,
    ) {}

    /**
     * Determines the current asset version.
     *
     * @see https://inertiajs.com/asset-versioning
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @see https://inertiajs.com/shared-data
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        $i18n = $this->localization->getPayload($request);
        if ($i18n['area'] === 'public') {
            $i18n['alternateUrls'] = $this->alternateUrls($request);
        }

        return [
            ...parent::share($request),
            'name' => config('app.name'),
            'auth' => [
                'user' => $request->user(),
                'canRegister' => Features::enabled(Features::registration()),
                'can' => [
                    'accessAdminPanel' => $request->user()?->canAccessAdminPanel() ?? false,
                    'manageUsers' => $request->user()?->can('viewAny', User::class) ?? false,
                    'viewAudit' => $request->user()?->can('viewAny', AuditLog::class) ?? false,
                    'manageSiteSettings' => $request->user()?->can('update', SiteSetting::class) ?? false,
                ],
            ],
            'locale' => app()->getLocale(),
            'i18n' => $i18n,
            'seo' => $this->seoDefaults($request),
            'sidebarOpen' => ! $request->hasCookie('sidebar_state') || $request->cookie('sidebar_state') === 'true',
            'site' => fn () => $this->siteSettings->current(),
            ...$this->publicNavigation($i18n['area'], $i18n['locale']),
            ...$this->designSystemShowcase($i18n['area']),
        ];
    }

    /**
     * URL of the local-only design-system showcase for the panel sidebar.
     * The route exists only in the `local` environment, so the link (and
     * this prop) is absent everywhere else.
     *
     * @return array{designSystemUrl?: string}
     */
    protected function designSystemShowcase(string $area): array
    {
        if ($area !== 'admin' || ! Route::has('admin.design-system')) {
            return [];
        }

        return ['designSystemUrl' => route('admin.design-system', absolute: false)];
    }

    /**
     * Lazily resolved header and footer menus, shared only with public pages.
     *
     * @return array<string, \Closure(): NavigationData>
     */
    protected function publicNavigation(string $area, string $locale): array
    {
        if ($area !== 'public') {
            return [];
        }

        return ['navigation' => fn () => $this->navigation->shared($locale)];
    }

    /**
     * Language alternates of the current public route for the locale
     * switcher. Routes whose parameters are translated per locale (e.g. page
     * slugs) are skipped: substituting the same value in every locale would
     * produce wrong URLs, so their controllers share the correct alternates
     * themselves (`Inertia::share('i18n.alternateUrls', ...)`).
     *
     * @return array<string, string>
     */
    protected function alternateUrls(Request $request): array
    {
        $currentRoute = $request->route();
        $currentRouteName = $currentRoute ? $currentRoute->getName() : null;

        if (! $currentRoute || ! $currentRouteName) {
            return $this->urlGenerator->getAlternateUrls('home');
        }

        $baseRouteName = str_starts_with($currentRouteName, 'localized.')
            ? substr($currentRouteName, 10)
            : $currentRouteName;

        if (! Route::has($baseRouteName)) {
            return $this->urlGenerator->getAlternateUrls('home');
        }

        if (in_array($baseRouteName, self::TRANSLATED_PARAMETER_ROUTES, true)) {
            return [];
        }

        $parameters = $currentRoute->parameters();
        unset($parameters['locale']);

        return $this->urlGenerator->getAlternateUrls($baseRouteName, $parameters);
    }

    /**
     * Organization name before the settings are saved: `seo.organization.name`,
     * then the site name.
     */
    protected function organizationName(): string
    {
        $configured = config('seo.organization.name');

        return is_string($configured) && $configured !== ''
            ? $configured
            : $this->siteSettings->siteName();
    }

    /**
     * Site-wide SEO defaults. The canonical URL is the current URL without
     * the query string; public URLs already carry their locale prefix and
     * the default locale alias (`/{default}/...`) redirects to it.
     */
    protected function seoDefaults(Request $request): SeoDefaultsData
    {
        $appUrl = url('/');
        $organizationUrl = config('seo.organization.url');
        $organizationLogo = config('seo.organization.logo');
        $siteLogo = $this->siteSettings->current()->logo;

        return new SeoDefaultsData(
            siteName: $this->siteSettings->siteName(),
            canonical: $request->url(),
            defaultImage: $this->siteSettings->defaultImageUrl(),
            defaultTitle: $this->siteSettings->defaultTitle(),
            defaultDescription: $this->siteSettings->defaultDescription(),
            organization: new SeoOrganizationData(
                name: $this->siteSettings->exists()
                    ? $this->siteSettings->siteName()
                    : $this->organizationName(),
                url: is_string($organizationUrl) && $organizationUrl !== '' ? url($organizationUrl) : $appUrl,
                logo: $siteLogo !== null
                    ? $siteLogo->src
                    : (is_string($organizationLogo) && $organizationLogo !== '' ? url($organizationLogo) : null),
            ),
        );
    }
}
