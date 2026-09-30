<?php

use App\Http\Controllers\Contact\ContactMessageController;
use App\Http\Controllers\Content\HomeController;
use App\Http\Controllers\Content\PublicArticleController;
use App\Http\Requests\Admin\Pages\PageTranslationRules;
use Illuminate\Support\Facades\Route;

Route::get('/', HomeController::class)->name('home');

Route::get('/articles', [PublicArticleController::class, 'index'])->name('articles.index');
Route::get('/articles/{slug}', [PublicArticleController::class, 'show'])
    ->where('slug', PageTranslationRules::SLUG_ROUTE_PATTERN)
    ->name('articles.show');

// Not `/contact`: first path segments of routes are reserved page slugs, and
// editors should be able to publish a CMS page called "contact".
Route::post('/contact-messages', [ContactMessageController::class, 'store'])
    ->middleware('throttle:contact')
    ->name('contact.store');

// Minimal static route used to verify LocalizedUrlGenerator against a route
// actually registered here, not one dynamically added inside a test.
Route::get('/about', fn () => 'about')->name('about');
