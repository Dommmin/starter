<?php

use App\Http\Controllers\Auth\DemoLoginController;
use Illuminate\Support\Facades\Route;

/*
| One-click sign-in of the public demo. web.php requires this file only when
| config('demo.enabled') is on, so the route does not exist (404) otherwise.
*/
Route::post('/_demo/login', DemoLoginController::class)
    ->middleware(['guest', 'throttle:10,1'])
    ->name('demo.login');
