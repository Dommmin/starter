<?php

use Illuminate\Support\Facades\Route;

Route::inertia('/', 'welcome')->name('home');

// Minimal static route used to verify LocalizedUrlGenerator against a route
// actually registered here, not one dynamically added inside a test.
Route::get('/about', fn () => 'about')->name('about');
