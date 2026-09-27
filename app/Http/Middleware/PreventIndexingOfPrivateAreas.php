<?php

namespace App\Http\Middleware;

use App\Services\Localization\LocalizationManager;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Marks the panel, account settings, authentication flows and the Horizon
 * dashboard as non-indexable with `X-Robots-Tag`, independently of their
 * layouts.
 */
class PreventIndexingOfPrivateAreas
{
    public function __construct(protected LocalizationManager $localization) {}

    /**
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        if ($this->isPrivate($request)) {
            $response->headers->set('X-Robots-Tag', 'noindex, nofollow');
        }

        return $response;
    }

    protected function isPrivate(Request $request): bool
    {
        $area = $request->attributes->get('localization.area') ?? $this->localization->determineArea($request);

        return $area === 'admin'
            || $this->localization->isAuthPath($request)
            || $this->isHorizonPath($request);
    }

    protected function isHorizonPath(Request $request): bool
    {
        $path = trim((string) config('horizon.path', 'horizon'), '/');

        return $path !== '' && $request->is($path, $path.'/*');
    }
}
