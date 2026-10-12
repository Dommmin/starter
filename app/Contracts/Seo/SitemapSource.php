<?php

namespace App\Contracts\Seo;

/**
 * A module that contributes canonical, indexable public URLs to the XML
 * sitemap. Register the class in App\Providers\SitemapServiceProvider; only
 * published records may be returned (never drafts or private data).
 */
interface SitemapSource
{
    /**
     * @return iterable<array{loc: string, alternates: array<string, string>, lastmod: string|null}>
     */
    public function entries(): iterable;
}
