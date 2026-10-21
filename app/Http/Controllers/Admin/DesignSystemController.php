<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\Localization\LocalizationManager;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DesignSystemController extends Controller
{
    /**
     * Display the local design-system showcase: every registered component
     * family in all of its states, with synthetic demo data only.
     *
     * The route is registered in the `local` environment only (see
     * routes/design-system.php); the environment check here is a second
     * guard in case the route file is ever loaded elsewhere. The admin
     * catalog does not carry the showcase labels, so the `showcase` group
     * is added to this page's payload alone.
     */
    public function __invoke(Request $request, LocalizationManager $localization): Response
    {
        abort_unless(app()->environment('local'), 404);

        $locale = $localization->getCurrentLocale($request);

        Inertia::share('i18n.messages.showcase', trans('showcase', [], $locale));

        return Inertia::render('admin/design-system/index');
    }
}
