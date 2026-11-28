<?php

use App\Http\Controllers\Booking\BookingController;
use App\Http\Controllers\Contact\ContactMessageController;
use App\Http\Controllers\Content\HomeController;
use App\Http\Controllers\Content\PublicArticleController;
use App\Http\Controllers\Newsletter\NewsletterSubscriptionController;
use App\Http\Requests\Admin\Pages\PageTranslationRules;
use Illuminate\Support\Facades\Route;

Route::get('/', HomeController::class)->name('home');

Route::get('/articles', [PublicArticleController::class, 'index'])->name('articles.index');
Route::get('/articles/{slug}', [PublicArticleController::class, 'show'])
    ->where('slug', PageTranslationRules::SLUG_ROUTE_PATTERN)
    ->name('articles.show');

// app:make-resource: public routes

Route::post('/newsletter-subscriptions', [NewsletterSubscriptionController::class, 'store'])
    ->middleware('throttle:5,1')
    ->name('newsletter.store');

Route::get('/book-appointment', BookingController::class)->name('booking');

// Not `/contact`: first path segments of routes are reserved page slugs, and
// editors should be able to publish a CMS page called "contact".
Route::post('/contact-messages', [ContactMessageController::class, 'store'])
    ->middleware('throttle:contact')
    ->name('contact.store');
