<?php

namespace App\Http\Controllers\Admin;

use App\Actions\Admin\BuildDashboardOverview;
use App\Http\Controllers\Controller;
use App\Models\SiteSetting;
use App\Models\User;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    /**
     * Display the panel dashboard: what needs attention, key figures and
     * trends; technical details live on the system status page.
     */
    public function index(Request $request, BuildDashboardOverview $buildOverview): Response
    {
        /** @var User $user */
        $user = $request->user();

        return Inertia::render('admin/index', [
            'overview' => $buildOverview->handle($user),
            // Only administrators (who may open the system status) are told
            // about risky platform settings.
            'systemWarnings' => $user->can('view', SiteSetting::class) ? [
                'debugOutsideLocal' => (bool) config('app.debug') && ! app()->environment('local'),
                'twoFactorOptional' => ! (bool) config('fortify.require_two_factor_for_admin'),
            ] : null,
        ]);
    }
}
