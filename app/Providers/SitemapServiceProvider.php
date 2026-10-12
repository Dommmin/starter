<?php

namespace App\Providers;

use App\Contracts\Seo\SitemapSource;
use Illuminate\Support\ServiceProvider;

/**
 * Sitemap sources of generated public modules, collected by
 * App\Actions\Seo\BuildSitemap through the SitemapSource tag. Pages and
 * articles are built into BuildSitemap itself.
 */
class SitemapServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        $this->app->tag([
            // app:make-resource: sitemap sources
        ], SitemapSource::class);
    }
}
