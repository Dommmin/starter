<?php

namespace App\Http\Controllers\Admin;

use App\Actions\Settings\BuildSiteLaunchChecklist;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class SystemStatusController extends Controller
{
    /**
     * Technical status for administrators: runtime, security policy and
     * platform modules (moved off the dashboard, which every panel user sees).
     */
    public function index(Request $request, BuildSiteLaunchChecklist $launchChecklist): Response
    {
        /** @var User $user */
        $user = $request->user();

        return Inertia::render('admin/system', [
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
                ['key' => 'identity', 'status' => 'active'],
                ['key' => 'content', 'status' => 'active'],
                ['key' => 'media', 'status' => 'active'],
                ['key' => 'audit', 'status' => 'ready'],
            ],
            'adminSettings' => [
                'defaultAdminLocale' => (string) config('localization.admin_default', 'en'),
            ],
            'launch' => $launchChecklist->handle($user),
        ]);
    }
}
