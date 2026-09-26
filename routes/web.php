<?php

use App\Services\Localization\LocalizationConfig;
use Illuminate\Support\Facades\Route;

require __DIR__.'/front.php';
require __DIR__.'/auth.php';

// Alias for default public locale: single 301 redirect to canonical root
$localizationConfig = app(LocalizationConfig::class);
$defaultLocale = $localizationConfig->getPublicDefault();
Route::get("/{$defaultLocale}", fn () => redirect('/', 301));
Route::get("/{$defaultLocale}/{path}", function (string $path) {
    if (str_starts_with($path, 'admin')) {
        abort(404);
    }

    return redirect("/{$path}", 301);
})->where('path', '.*');

// Extra active public locales with prefix: /{locale}
$extraLocales = array_values(array_diff($localizationConfig->getPublicLocales(), [$defaultLocale]));
if (! empty($extraLocales)) {
    Route::prefix('{locale}')
        ->whereIn('locale', $extraLocales)
        ->as('localized.')
        ->middleware(['web'])
        ->group(function () {
            require __DIR__.'/front.php';
            require __DIR__.'/auth.php';
        });
}

require __DIR__.'/settings.php';
require __DIR__.'/admin.php';
