<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\Response;

/**
 * On the public demo (`config('demo.enabled')`) every request that could
 * change data is refused, whoever sends it: only signing in and out pass.
 * Pages show a toast, JSON clients get 403.
 */
class BlockChangesInDemo
{
    /**
     * Routes that stay usable on the demo.
     *
     * @var list<string>
     */
    private const array ALLOWED_ROUTES = [
        'login.store',
        'localized.login.store',
        'logout',
        'localized.logout',
        'two-factor.login.store',
        'localized.two-factor.login.store',
        'passkey.login',
        'demo.login',
    ];

    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (! config('demo.enabled') || $request->isMethodSafe() || $request->routeIs(...self::ALLOWED_ROUTES)) {
            return $next($request);
        }

        if ($request->expectsJson() && ! $request->header('X-Inertia')) {
            return response()->json(['message' => __('common.demo.blocked')], Response::HTTP_FORBIDDEN);
        }

        Inertia::flash('toast', ['type' => 'error', 'message' => __('common.demo.blocked')]);

        return back(303);
    }
}
