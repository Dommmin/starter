<?php

use App\Http\Controllers\Admin\Articles\ArticleController;
use App\Http\Controllers\Admin\Audit\AuditLogController;
use App\Http\Controllers\Admin\Contact\ContactMessageController;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\Faqs\FaqController;
use App\Http\Controllers\Admin\HomeSections\HomeSectionController;
use App\Http\Controllers\Admin\Media\MediaAssetController;
use App\Http\Controllers\Admin\Navigation\MenuItemController;
use App\Http\Controllers\Admin\Pages\PageController;
use App\Http\Controllers\Admin\Settings\SiteSettingsController;
use App\Http\Controllers\Admin\UserIndexController;
use App\Http\Controllers\Admin\Users\UserController;
use App\Http\Middleware\EnsureCanAccessAdminPanel;
use App\Models\Article;
use App\Models\AuditLog;
use App\Models\ContactMessage;
use App\Models\Faq;
use App\Models\HomeSection;
use App\Models\MediaAsset;
use App\Models\MenuItem;
use App\Models\Page;
use App\Models\SiteSetting;
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
        Route::get('/users/create', [UserController::class, 'create'])
            ->name('users.create')
            ->can('create', User::class);
        Route::post('/users', [UserController::class, 'store'])
            ->name('users.store')
            ->can('create', User::class)
            ->middleware('password.confirm');
        Route::get('/users/{user}/edit', [UserController::class, 'edit'])
            ->name('users.edit')
            ->can('update', 'user');
        Route::put('/users/{user}', [UserController::class, 'update'])
            ->name('users.update')
            ->can('update', 'user')
            ->middleware('password.confirm');
        Route::delete('/users/{user}', [UserController::class, 'destroy'])
            ->name('users.destroy')
            ->can('delete', 'user')
            ->middleware('password.confirm');
        Route::get('/audit', [AuditLogController::class, 'index'])
            ->name('audit.index')
            ->can('viewAny', AuditLog::class);

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

        Route::get('/articles', [ArticleController::class, 'index'])
            ->name('articles.index')
            ->can('viewAny', Article::class);
        Route::get('/articles/create', [ArticleController::class, 'create'])
            ->name('articles.create')
            ->can('create', Article::class);
        Route::post('/articles', [ArticleController::class, 'store'])
            ->name('articles.store')
            ->can('create', Article::class);
        Route::get('/articles/{article}/edit', [ArticleController::class, 'edit'])
            ->name('articles.edit')
            ->can('update', 'article');
        Route::put('/articles/{article}', [ArticleController::class, 'update'])
            ->name('articles.update')
            ->can('update', 'article');
        Route::delete('/articles/{article}', [ArticleController::class, 'destroy'])
            ->name('articles.destroy')
            ->can('delete', 'article');

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

        Route::get('/media', [MediaAssetController::class, 'index'])
            ->name('media.index')
            ->can('viewAny', MediaAsset::class);
        Route::get('/media/picker', [MediaAssetController::class, 'picker'])
            ->name('media.picker')
            ->can('viewAny', MediaAsset::class);
        Route::post('/media', [MediaAssetController::class, 'store'])
            ->name('media.store')
            ->can('create', MediaAsset::class);
        Route::get('/media/{mediaAsset}/edit', [MediaAssetController::class, 'edit'])
            ->name('media.edit')
            ->can('view', 'mediaAsset');
        Route::get('/media/{mediaAsset}/download', [MediaAssetController::class, 'download'])
            ->name('media.download')
            ->can('view', 'mediaAsset');
        Route::get('/media/{mediaAsset}/preview', [MediaAssetController::class, 'preview'])
            ->name('media.preview')
            ->can('view', 'mediaAsset');
        Route::put('/media/{mediaAsset}', [MediaAssetController::class, 'update'])
            ->name('media.update')
            ->can('update', 'mediaAsset');
        Route::delete('/media/{mediaAsset}', [MediaAssetController::class, 'destroy'])
            ->name('media.destroy')
            ->can('delete', 'mediaAsset');

        Route::get('/contact', [ContactMessageController::class, 'index'])
            ->name('contact.index')
            ->can('viewAny', ContactMessage::class);
        Route::get('/contact/{contactMessage}', [ContactMessageController::class, 'show'])
            ->name('contact.show')
            ->can('view', 'contactMessage');
        Route::post('/contact/{contactMessage}/retry', [ContactMessageController::class, 'retry'])
            ->name('contact.retry')
            ->can('retry', 'contactMessage');
        Route::delete('/contact/{contactMessage}', [ContactMessageController::class, 'destroy'])
            ->name('contact.destroy')
            ->can('delete', 'contactMessage');

        Route::get('/site-settings', [SiteSettingsController::class, 'edit'])
            ->name('site-settings.edit')
            ->can('view', SiteSetting::class);
        Route::put('/site-settings', [SiteSettingsController::class, 'update'])
            ->name('site-settings.update')
            ->can('update', SiteSetting::class);
        Route::get('/navigation', [MenuItemController::class, 'index'])
            ->name('navigation.index')
            ->can('viewAny', MenuItem::class);
        Route::get('/navigation/create', [MenuItemController::class, 'create'])
            ->name('navigation.create')
            ->can('create', MenuItem::class);
        Route::post('/navigation', [MenuItemController::class, 'store'])
            ->name('navigation.store')
            ->can('create', MenuItem::class);
        Route::put('/navigation/order', [MenuItemController::class, 'reorder'])
            ->name('navigation.reorder')
            ->can('reorder', MenuItem::class);
        Route::get('/navigation/{menuItem}/edit', [MenuItemController::class, 'edit'])
            ->name('navigation.edit')
            ->can('update', 'menuItem');
        Route::put('/navigation/{menuItem}', [MenuItemController::class, 'update'])
            ->name('navigation.update')
            ->can('update', 'menuItem');
        Route::delete('/navigation/{menuItem}', [MenuItemController::class, 'destroy'])
            ->name('navigation.destroy')
            ->can('delete', 'menuItem');
        Route::get('/home-sections', [HomeSectionController::class, 'index'])
            ->name('home-sections.index')
            ->can('viewAny', HomeSection::class);
        Route::put('/home-sections/order', [HomeSectionController::class, 'reorder'])
            ->name('home-sections.reorder')
            ->can('reorder', HomeSection::class);
        Route::get('/home-sections/{homeSection}/edit', [HomeSectionController::class, 'edit'])
            ->whereNumber('homeSection')
            ->name('home-sections.edit')
            ->can('update', 'homeSection');
        Route::put('/home-sections/{homeSection}', [HomeSectionController::class, 'update'])
            ->whereNumber('homeSection')
            ->name('home-sections.update')
            ->can('update', 'homeSection');
        Route::patch('/home-sections/{homeSection}/visibility', [HomeSectionController::class, 'visibility'])
            ->whereNumber('homeSection')
            ->name('home-sections.visibility')
            ->can('update', 'homeSection');
    });
