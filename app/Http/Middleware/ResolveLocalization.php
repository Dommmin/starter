<?php

namespace App\Http\Middleware;

use App\Services\Localization\LocalizationManager;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ResolveLocalization
{
    public function __construct(protected LocalizationManager $localization) {}

    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $area = $this->localization->determineArea($request);
        $locale = $this->localization->resolveLocale($request, $area);

        $this->localization->setContext($area, $locale, $request);
        app()->setLocale($locale);

        $fallback = $area === 'admin'
            ? $this->localization->getConfig()->getAdminFallback()
            : $this->localization->getConfig()->getPublicFallback();
        app()->setFallbackLocale($fallback);

        $response = $next($request);

        return $response;
    }
}
