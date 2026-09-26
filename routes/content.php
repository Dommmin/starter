<?php

use App\Http\Controllers\Content\PublicPageController;
use App\Http\Requests\Admin\Pages\PageTranslationRules;
use Illuminate\Support\Facades\Route;

/*
| Content pages resolved by slug. This file is loaded last (see web.php) so
| the single-segment catch-all never shadows system, auth, settings or admin
| routes; slugs of those paths are also rejected by PageTranslationRules.
*/
Route::get('/{slug}', [PublicPageController::class, 'show'])
    ->where('slug', PageTranslationRules::SLUG_ROUTE_PATTERN)
    ->name('pages.show');
