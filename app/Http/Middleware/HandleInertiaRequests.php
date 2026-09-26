<?php

namespace App\Http\Middleware;

use App\Data\Seo\SeoDefaultsData;
use App\Data\Seo\SeoOrganizationData;
use App\Models\AuditLog;
use App\Models\User;
use App\Services\Localization\LocalizationManager;
use App\Services\Localization\LocalizedUrlGenerator;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Inertia\Middleware;

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
    private const TRANSLATED_PARAMETER_ROUTES = ['pages.show'];

    public function __construct(
        protected LocalizationManager $localization,
        protected LocalizedUrlGenerator $urlGenerator,
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
                'can' => [
                    'manageUsers' => $request->user()?->can('viewAny', User::class) ?? false,
                    'viewAudit' => $request->user()?->can('viewAny', AuditLog::class) ?? false,
                ],
            ],
            'locale' => app()->getLocale(),
            'i18n' => $i18n,
            'seo' => $this->seoDefaults($request),
            'sidebarOpen' => ! $request->hasCookie('sidebar_state') || $request->cookie('sidebar_state') === 'true',
        ];
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
     * Site-wide SEO defaults. The canonical URL is the current URL without
     * the query string; public URLs already carry their locale prefix and
     * the default locale alias (`/{default}/...`) redirects to it.
     */
    protected function seoDefaults(Request $request): SeoDefaultsData
    {
        $appUrl = url('/');
        $organizationUrl = config('seo.organization.url');
        $organizationLogo = config('seo.organization.logo');
        $defaultImage = config('seo.default_image');

        return new SeoDefaultsData(
            siteName: (string) config('seo.site_name'),
            canonical: $request->url(),
            defaultImage: is_string($defaultImage) && $defaultImage !== '' ? url($defaultImage) : null,
            organization: new SeoOrganizationData(
                name: (string) config('seo.organization.name'),
                url: is_string($organizationUrl) && $organizationUrl !== '' ? url($organizationUrl) : $appUrl,
                logo: is_string($organizationLogo) && $organizationLogo !== '' ? url($organizationLogo) : null,
            ),
        );
    }
}
