<?php

namespace App\Http\Middleware;

use App\Repositories\Redirects\RedirectRepository;
use App\Support\Redirects\RedirectPath;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Answer GET/HEAD requests of a managed redirect source (admin module
 * "Redirects") with its 301/302, before routing: addresses of a former site
 * (`/oferta/strona.html`) match no route, so the web group would never run.
 * The query string is carried over unless the target has its own; panel,
 * settings, health and sign-in paths are never redirected. Each hit
 * increments the redirect's counter.
 */
class RedirectManagedPaths
{
    public function __construct(private readonly RedirectRepository $redirects) {}

    /**
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (! $request->isMethod('GET') && ! $request->isMethod('HEAD')) {
            return $next($request);
        }

        $path = RedirectPath::source($request->getPathInfo());
        $redirect = $path === '/' ? null : $this->redirects->findActive($path);

        if ($redirect === null || RedirectPath::isReserved($path)) {
            return $next($request);
        }

        $this->redirects->recordHit($redirect['id']);

        return redirect()->to(RedirectPath::withQuery($redirect['target'], $request->getQueryString()), $redirect['status']);
    }
}
