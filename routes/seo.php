<?php

use App\Http\Controllers\Seo\RobotsController;
use App\Http\Controllers\Seo\SitemapController;
use Illuminate\Support\Facades\Route;

/*
| Crawler endpoints outside locale prefixes. They are stateless and publicly
| cacheable, so the session/cookie/CSRF middleware of the web group is
| skipped (no Set-Cookie on a `Cache-Control: public` response).
*/
Route::get('/sitemap.xml', SitemapController::class)
    ->withoutMiddleware('web')
    ->name('sitemap');

Route::get('/robots.txt', RobotsController::class)
    ->withoutMiddleware('web')
    ->name('robots');
