<?php

use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\Faqs\FaqController;
use App\Http\Controllers\Admin\Pages\PageController;
use App\Http\Controllers\Admin\UserIndexController;
use App\Http\Middleware\EnsureCanAccessAdminPanel;
use App\Models\Faq;
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

        Route::get('/faqs', [FaqController::class, 'index'])
            ->name('faqs.index')
            ->can('viewAny', Faq::class);
        Route::get('/faqs/create', [FaqController::class, 'create'])
            ->name('faqs.create')
            ->can('create', Faq::class);
        Route::post('/faqs', [FaqController::class, 'store'])
            ->name('faqs.store')
            ->can('create', Faq::class);
        Route::get('/faqs/{faq}/edit', [FaqController::class, 'edit'])
            ->name('faqs.edit')
            ->can('update', 'faq');
        Route::put('/faqs/{faq}', [FaqController::class, 'update'])
            ->name('faqs.update')
            ->can('update', 'faq');
        Route::delete('/faqs/{faq}', [FaqController::class, 'destroy'])
            ->name('faqs.destroy')
            ->can('delete', 'faq');
    });
