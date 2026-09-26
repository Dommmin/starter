<?php

use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\Pages\PageController;
use App\Http\Controllers\Admin\UserIndexController;
use App\Http\Middleware\EnsureCanAccessAdminPanel;
use App\Models\Page;
use App\Models\User;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'verified', EnsureCanAccessAdminPanel::class])
    ->prefix('admin')
    ->name('admin.')
    ->group(function () {
        Route::get('/', [DashboardController::class, 'index'])->name('index');
        Route::get('/users', [UserIndexController::class, 'index'])
            ->name('users.index')
            ->can('viewAny', User::class);

        Route::get('/pages', [PageController::class, 'index'])
            ->name('pages.index')
            ->can('viewAny', Page::class);
        Route::get('/pages/create', [PageController::class, 'create'])
            ->name('pages.create')
            ->can('create', Page::class);
        Route::post('/pages', [PageController::class, 'store'])
            ->name('pages.store')
            ->can('create', Page::class);
        Route::get('/pages/{page}/edit', [PageController::class, 'edit'])
            ->name('pages.edit')
            ->can('update', 'page');
        Route::put('/pages/{page}', [PageController::class, 'update'])
            ->name('pages.update')
            ->can('update', 'page');
        Route::delete('/pages/{page}', [PageController::class, 'destroy'])
            ->name('pages.destroy')
            ->can('delete', 'page');
    });
