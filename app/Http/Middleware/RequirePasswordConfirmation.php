<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Auth\Middleware\RequirePassword;

class RequirePasswordConfirmation extends RequirePassword
{
    /**
     * Require a recently confirmed password without navigating an Inertia app
     * away from its current screen.
     */
    public function handle(
        $request,
        Closure $next,
        $redirectToRoute = null,
        $passwordTimeoutSeconds = null,
    ) {
        $timeoutSeconds = $passwordTimeoutSeconds === null ? null : (int) $passwordTimeoutSeconds;

        if ($this->shouldConfirmPassword($request, $timeoutSeconds)) {
            if ($request->header('X-Inertia')) {
                return response()->json([
                    'message' => 'Password confirmation required.',
                ], 423, [
                    'X-Password-Confirmation-Required' => 'true',
                ]);
            }

            return parent::handle($request, $next, $redirectToRoute, $passwordTimeoutSeconds);
        }

        return $next($request);
    }
}
