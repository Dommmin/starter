<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    /**
     * Display the administration platform dashboard.
     */
    public function index(Request $request): Response
    {
        return Inertia::render('admin/index', [
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
