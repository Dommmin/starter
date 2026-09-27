<?php

use App\Http\Controllers\Contact\ContactMessageController;
use App\Http\Controllers\Content\HomeController;
use Illuminate\Support\Facades\Route;

Route::get('/', HomeController::class)->name('home');

// Not `/contact`: first path segments of routes are reserved page slugs, and
// editors should be able to publish a CMS page called "contact".
Route::post('/contact-messages', [ContactMessageController::class, 'store'])
    ->middleware('throttle:contact')
    ->name('contact.store');

// Minimal static route used to verify LocalizedUrlGenerator against a route
// actually registered here, not one dynamically added inside a test.
Route::get('/about', fn () => 'about')->name('about');
