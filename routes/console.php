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
