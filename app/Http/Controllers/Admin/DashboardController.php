<?php

namespace App\Http\Controllers\Admin;

use App\Actions\Admin\BuildDashboardOverview;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    /**
     * Display the panel dashboard: the content overview first, then the
     * platform status block.
     */
    public function index(Request $request, BuildDashboardOverview $buildOverview): Response
    {
        /** @var User $user */
        $user = $request->user();

        return Inertia::render('admin/index', [
            'overview' => $buildOverview->handle($user),
            'system' => [
                'appName' => (string) config('app.name'),
                'environment' => app()->environment(),
                'debugMode' => (bool) config('app.debug'),
                'phpVersion' => PHP_VERSION,
                'laravelVersion' => app()->version(),
                'databaseDriver' => (string) config('database.default'),
                'cacheDriver' => (string) config('cache.default'),
                'queueDriver' => (string) config('queue.default'),
            ],
            'security' => [
                'requireTwoFactorForAdmin' => (bool) config('fortify.require_two_factor_for_admin'),
                'sessionLifetime' => (int) config('session.lifetime'),
                'httpsEnabled' => $request->isSecure(),
            ],
            'modules' => [
                [
                    'key' => 'identity',
                    'status' => 'active',
                ],
                [
                    'key' => 'content',
                    'status' => 'active',
                ],
                [
                    'key' => 'media',
                    'status' => 'planned',
                ],
                [
                    'key' => 'audit',
                    'status' => 'ready',
                ],
            ],
            'adminSettings' => [
                'currentLocale' => app()->getLocale(),
                'defaultAdminLocale' => (string) config('localization.admin_default', 'en'),
                'twoFactorEnforced' => (bool) config('fortify.require_two_factor_for_admin'),
            ],
        ]);
    }
}
