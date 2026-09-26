<?php

use App\Jobs\Middleware\ExecuteInLocale;

class DummyLocaleJob
{
    public function __construct(public ?string $locale = null) {}

    public function middleware(): array
    {
        return [new ExecuteInLocale];
    }
}

test('job middleware isolates execution locale and resets on completion', function () {
    app()->setLocale('en');

    $job = new DummyLocaleJob('pl');
    $middleware = new ExecuteInLocale;

    $executedLocale = null;
    $middleware->handle($job, function () use (&$executedLocale) {
        $executedLocale = app()->getLocale();
    });

    expect($executedLocale)->toBe('pl')
        ->and(app()->getLocale())->toBe('en');
});

test('job middleware resets locale even after exception in job execution', function () {
    app()->setLocale('en');

    $job = new DummyLocaleJob('de');
    $middleware = new ExecuteInLocale;

    try {
        $middleware->handle($job, function () {
            expect(app()->getLocale())->toBe('de');
            throw new RuntimeException('Simulated job failure');
        });
    } catch (RuntimeException $e) {
        expect($e->getMessage())->toBe('Simulated job failure');
    }

    expect(app()->getLocale())->toBe('en');
});

test('subsequent jobs in different locales do not bleed state', function () {
    app()->setLocale('en');

    $middleware = new ExecuteInLocale;

    $firstLocale = null;
    try {
        $middleware->handle(new DummyLocaleJob('pl'), function () use (&$firstLocale) {
            $firstLocale = app()->getLocale();
            throw new Exception('First job failed');
        });
    } catch (Exception) {
        // Suppress first error
    }

    $secondLocale = null;
    $middleware->handle(new DummyLocaleJob('de'), function () use (&$secondLocale) {
        $secondLocale = app()->getLocale();
    });

    expect($firstLocale)->toBe('pl')
        ->and($secondLocale)->toBe('de')
        ->and(app()->getLocale())->toBe('en');
});

test('job without explicit locale defaults safely to admin english', function () {
    app()->setLocale('pl');

    $middleware = new ExecuteInLocale;
    $executedLocale = null;

    $middleware->handle(new DummyLocaleJob(null), function () use (&$executedLocale) {
        $executedLocale = app()->getLocale();
    });

    expect($executedLocale)->toBe('en')
        ->and(app()->getLocale())->toBe('pl');
});
