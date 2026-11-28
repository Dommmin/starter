<?php

namespace App\Http\Middleware;

use App\Enums\Module;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Routes of a disabled module answer 404, as if they did not exist. The
 * routes stay registered, so generated TypeScript routes do not depend on
 * the configuration of a deployment.
 */
class EnsureModuleEnabled
{
    /**
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next, string $module): Response
    {
        abort_unless(Module::from($module)->isEnabled(), 404);

        return $next($request);
    }
}
