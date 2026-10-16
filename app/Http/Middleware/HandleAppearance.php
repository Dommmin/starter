<?php

namespace App\Http\Middleware;

use App\Enums\ThemePreference;
use App\Repositories\Settings\SiteSettingsRepository;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\View;
use Symfony\Component\HttpFoundation\Response;

/**
 * Shares the document appearance with the Blade root view: the visitor's
 * `appearance` cookie, otherwise the default theme of the site settings,
 * and the site presets (accent, typography, favicon).
 */
class HandleAppearance
{
    public function __construct(private readonly SiteSettingsRepository $siteSettings) {}

    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $siteAppearance = $this->siteSettings->appearance();
        $stored = $request->cookie('appearance');
        $cookie = is_string($stored) ? ThemePreference::tryFrom($stored) : null;

        View::share('appearance', ($cookie ?? $siteAppearance->defaultTheme)->value);
        View::share('siteAppearance', $siteAppearance);

        return $next($request);
    }
}
