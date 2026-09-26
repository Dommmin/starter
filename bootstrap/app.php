<?php

use App\Data\Errors\ErrorPageData;
use App\Http\Middleware\HandleAppearance;
use App\Http\Middleware\HandleInertiaRequests;
use App\Http\Middleware\PreventIndexingOfPrivateAreas;
use App\Http\Middleware\RequirePasswordConfirmation;
use App\Http\Middleware\ResolveLocalization;
use App\Services\Localization\LocalizationManager;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Middleware\AddLinkHeadersForPreloadedAssets;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\Response;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // Reject spoofed Host headers outside local/testing so canonical,
        // hreflang and sitemap URLs always use the configured APP_URL host.
        $middleware->trustHosts();

        $middleware->encryptCookies(except: ['appearance', 'sidebar_state']);

        $middleware->web(append: [
            HandleAppearance::class,
            ResolveLocalization::class,
            PreventIndexingOfPrivateAreas::class,
            HandleInertiaRequests::class,
            // The HTML still carries every modulepreload tag; the header is
            // capped so a split bundle cannot overflow proxy/FastCGI header buffers.
            AddLinkHeadersForPreloadedAssets::using(20),
        ]);

        $middleware->alias([
            'password.confirm' => RequirePasswordConfirmation::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );

        /*
         * HTTP errors of HTML requests render the Inertia `errors/show` page
         * (SSR, shared data, noindex). With APP_DEBUG the 500 keeps Laravel's
         * debug page. Unmatched routes never ran ResolveLocalization, so the
         * locale is resolved here from the URL before shared data is (re)built.
         */
        $exceptions->respond(function (Response $response, Throwable $exception, Request $request): Response {
            $status = $response->getStatusCode();

            if (
                ! in_array($status, [403, 404, 500, 503], true)
                || ($status === 500 && config('app.debug'))
                || $request->is('api/*')
                || $request->expectsJson()
            ) {
                return $response;
            }

            $localization = app(LocalizationManager::class);
            if (! $request->attributes->has('localization.locale')) {
                $area = $localization->determineArea($request);
                $locale = $localization->resolveLocale($request, $area);
                $localization->setContext($area, $locale, $request);
                app()->setLocale($locale);
            }

            try {
                $inertiaMiddleware = app(HandleInertiaRequests::class);
                Inertia::version(fn () => $inertiaMiddleware->version($request));
                Inertia::share($inertiaMiddleware->share($request));

                $errorResponse = Inertia::render('errors/show', new ErrorPageData(status: $status))
                    ->toResponse($request)
                    ->setStatusCode($status);
                $errorResponse->headers->set('X-Robots-Tag', 'noindex, nofollow');

                return $errorResponse;
            } catch (Throwable $renderException) {
                report($renderException);

                return $response;
            }
        });
    })->create();
