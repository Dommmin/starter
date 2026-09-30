<?php

use App\Http\Controllers\Admin\DesignSystemController;
use App\Http\Middleware\EnsureCanAccessAdminPanel;
use Illuminate\Support\Facades\Route;

/*
| Local-only design-system showcase. web.php requires this file only when
| APP_ENV=local, so the route does not exist (404) in any other environment.
*/
Route::middleware(['auth', 'verified', EnsureCanAccessAdminPanel::class])
    ->prefix('admin')
    ->name('admin.')
    ->group(function () {
        Route::get('/design-system', DesignSystemController::class)->name('design-system');
    });
