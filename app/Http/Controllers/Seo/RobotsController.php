<?php

namespace App\Http\Controllers\Seo;

use App\Http\Controllers\Controller;
use App\Repositories\Settings\SiteSettingsRepository;
use Illuminate\Http\Response;

class RobotsController extends Controller
{
    /**
     * Only production may be crawled; every other environment (staging,
     * preview, local) disallows everything, as does production while the
     * site-wide noindex switch or the coming soon page is on.
     */
    public function __invoke(SiteSettingsRepository $siteSettings): Response
    {
        $lines = app()->isProduction() && ! $siteSettings->isNoindex()
            ? [
                'User-agent: *',
                'Allow: /',
                'Disallow: /admin',
                'Disallow: /settings',
                '',
                'Sitemap: '.route('sitemap'),
            ]
            : [
                'User-agent: *',
                'Disallow: /',
            ];

        return response(implode("\n", $lines)."\n", 200, [
            'Content-Type' => 'text/plain; charset=UTF-8',
            'Cache-Control' => 'public, max-age='.(int) config('seo.cache_max_age'),
        ]);
    }
}
