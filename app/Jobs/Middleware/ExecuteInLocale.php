<?php

namespace App\Jobs\Middleware;

use Closure;

class ExecuteInLocale
{
    public function __construct(protected ?string $locale = null) {}

    /**
     * Process the queued job with isolated locale context.
     *
     * @param  Closure(mixed): mixed  $next
     */
    public function handle(mixed $job, Closure $next): mixed
    {
        $targetLocale = $this->locale
            ?? (isset($job->locale) && is_string($job->locale) ? $job->locale : null)
            ?? config('localization.admin_default', 'en');

        $originalLocale = app()->getLocale();

        try {
            app()->setLocale($targetLocale);

            return $next($job);
        } finally {
            app()->setLocale($originalLocale);
        }
    }
}
