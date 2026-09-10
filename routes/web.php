<?php

use App\Http\Middleware\EnsureCanAccessAdminPanel;
use Illuminate\Support\Facades\Route;

Route::inertia('/', 'welcome')->name('home');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::inertia('admin', 'admin/index')
        ->middleware(EnsureCanAccessAdminPanel::class)
        ->name('admin.index');
});

require __DIR__.'/settings.php';
