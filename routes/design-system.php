<?php

use App\Http\Controllers\Admin\DesignSystemController;
use App\Http\Controllers\Content\PublicDesignSystemController;
use App\Http\Middleware\EnsureCanAccessAdminPanel;
use App\Services\Localization\LocalizationConfig;
use Illuminate\Support\Facades\Route;

/*
| Local-only design-system showcases. web.php requires this file only when
| APP_ENV=local, so the routes do not exist (404) in any other environment.
*/
Route::middleware(['auth', 'verified', EnsureCanAccessAdminPanel::class])
    ->prefix('admin')
    ->name('admin.')
    ->group(function () {
        Route::get('/design-system', DesignSystemController::class)->name('design-system');
    });

/*
| Public (WEB) components in the public frame with SSR. The leading
| underscore keeps the path outside the page slug pattern
| (PageTranslationRules::SLUG_ROUTE_PATTERN), so it never collides with a
| CMS page and needs no reserved slug. Extra public locales get a prefixed
| variant, so the locale switcher and screen-level i18n can be reviewed.
*/
Route::get('/_design-system', PublicDesignSystemController::class)->name('design-system');

$designSystemConfig = app(LocalizationConfig::class);
$designSystemLocales = array_values(array_diff(
    $designSystemConfig->getPublicLocales(),
    [$designSystemConfig->getPublicDefault()],
));

if ($designSystemLocales !== []) {
    Route::prefix('{locale}')
        ->whereIn('locale', $designSystemLocales)
        ->as('localized.')
        ->group(function () {
            Route::get('/_design-system', PublicDesignSystemController::class)->name('design-system');
        });
}
