<?php

use App\Http\Controllers\Auth\DevLoginController;
use Illuminate\Support\Facades\Route;

/*
| Local-only one-click administrator login. web.php requires this file only
| when APP_ENV=local, so the route does not exist (404) in any other
| environment.
*/
Route::post('/_dev/login-as-admin', DevLoginController::class)
    ->middleware(['guest', 'throttle:10,1'])
    ->name('dev.login');
