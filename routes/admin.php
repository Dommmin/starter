<?php

use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\UserIndexController;
use App\Http\Middleware\EnsureCanAccessAdminPanel;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'verified', EnsureCanAccessAdminPanel::class])
    ->prefix('admin')
    ->name('admin.')
    ->group(function () {
        Route::get('/', [DashboardController::class, 'index'])->name('index');
        Route::get('/users', [UserIndexController::class, 'index'])->name('users.index');
    });
