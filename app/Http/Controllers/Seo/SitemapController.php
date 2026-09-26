<?php

namespace App\Http\Controllers\Seo;

use App\Actions\Seo\BuildSitemap;
use App\Http\Controllers\Controller;
use Illuminate\Http\Response;

class SitemapController extends Controller
{
    public function __invoke(BuildSitemap $buildSitemap): Response
    {
        return response($buildSitemap->handle(), 200, [
            'Content-Type' => 'application/xml; charset=UTF-8',
            'Cache-Control' => 'public, max-age='.(int) config('seo.cache_max_age'),
        ]);
    }
}
