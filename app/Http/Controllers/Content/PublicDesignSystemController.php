<?php

namespace App\Http\Controllers\Content;

use App\Http\Controllers\Controller;
use App\Services\Localization\LocalizationManager;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\Response;

class PublicDesignSystemController extends Controller
{
    /**
     * Display the local showcase of the public (WEB) components in the
     * public frame with SSR, with synthetic demo data only.
     *
     * The routes are registered in the `local` environment only (see
     * routes/design-system.php); the environment check here is a second
     * guard in case the route file is ever loaded elsewhere. Public pages
     * receive only the public catalog, so the showcase labels
     * (`admin.designSystem.*`) are added to this page's payload alone.
     */
    public function __invoke(Request $request, LocalizationManager $localization): Response
    {
        abort_unless(app()->environment('local'), 404);

        $locale = $localization->getCurrentLocale($request);

        Inertia::share('i18n.messages.admin.designSystem', trans('admin.designSystem', [], $locale));

        $response = Inertia::render('design-system/index')->toResponse($request);
        $response->headers->set('X-Robots-Tag', 'noindex, nofollow');

        return $response;
    }
}
