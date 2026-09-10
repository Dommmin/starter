<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureCanAccessAdminPanel
{
    /**
     * Require temporary admin access and confirmed two-factor authentication.
     *
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        abort_unless($user?->isAdmin(), Response::HTTP_FORBIDDEN);

        if (config('fortify.require_two_factor_for_admin') && $user->two_factor_confirmed_at === null) {
            return to_route('security.edit');
        }

        return $next($request);
    }
}
