<?php

use App\Http\Controllers\Health\ReadinessController;
use Illuminate\Support\Facades\Route;

/*
| Readiness probe outside locale prefixes. Stateless: the web group
| (session, cookies, CSRF, Inertia) is skipped. Liveness stays on Laravel's
| built-in /up (bootstrap/app.php).
*/
Route::get('/health/ready', ReadinessController::class)
    ->withoutMiddleware('web')
    ->middleware('throttle:health')
    ->name('health.ready');
