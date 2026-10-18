<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('audit:prune')
    ->daily()
    ->onOneServer()
    ->withoutOverlapping();

Schedule::command('contact:retry-failed')
    ->everyFifteenMinutes()
    ->onOneServer()
    ->withoutOverlapping();

Schedule::command('contact:prune')
    ->daily()
    ->onOneServer()
    ->withoutOverlapping();

Schedule::command('content:purge-trash')
    ->daily()
    ->onOneServer()
    ->withoutOverlapping();

Schedule::command('ops:heartbeat')
    ->everyMinute()
    ->onOneServer();

Schedule::command('ops:check-backup')
    ->hourly()
    ->onOneServer()
    ->withoutOverlapping();

Schedule::command('horizon:snapshot')
    ->everyFiveMinutes()
    ->onOneServer();

Schedule::command('demo:reset')
    ->hourly()
    ->onOneServer()
    ->withoutOverlapping()
    ->when(fn (): bool => (bool) config('demo.enabled'));
