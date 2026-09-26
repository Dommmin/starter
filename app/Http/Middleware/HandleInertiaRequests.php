<?php

namespace App\Http\Middleware;

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
            $currentRoute = $request->route();
            $currentRouteName = $currentRoute ? $currentRoute->getName() : null;

            $baseRouteName = 'home';
            $parameters = [];

            if ($currentRouteName) {
                $candidateName = str_starts_with($currentRouteName, 'localized.')
                    ? substr($currentRouteName, 10)
                    : $currentRouteName;

                if (Route::has($candidateName)) {
                    $baseRouteName = $candidateName;
                    $parameters = $currentRoute->parameters();
                    unset($parameters['locale']);
                }
            }

            $i18n['alternateUrls'] = $this->urlGenerator->getAlternateUrls($baseRouteName, $parameters);
        }

        return [
            ...parent::share($request),
            'name' => config('app.name'),
            'auth' => [
                'user' => $request->user(),
            ],
            'locale' => app()->getLocale(),
            'i18n' => $i18n,
            'sidebarOpen' => ! $request->hasCookie('sidebar_state') || $request->cookie('sidebar_state') === 'true',
        ];
    }
}
