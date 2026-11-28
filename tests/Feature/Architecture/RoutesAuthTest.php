<?php

declare(strict_types=1);

use App\Services\Localization\LocalizationConfig;
use Illuminate\Routing\Route as RoutingRoute;
use Illuminate\Support\Facades\Route;

// Zmienne i domknięcia zamiast funkcji globalnych: funkcja o tej samej nazwie w tests/Pest.php
// albo innym pliku testów kończy się błędem „Cannot redeclare”.

/*
 * Każda trasa aplikacji wymaga uwierzytelnienia (middleware auth, auth:sanctum, Authenticate),
 * poza listą tras publicznych. Nowa trasa publiczna to decyzja: dopisz ją tu z powodem w tym
 * samym MR. Dzięki temu testy funkcji nie powtarzają 401 na każdej trasie.
 */
$routesAuthPublic = [
    'GET login' => 'formularz logowania (Fortify)',
    'POST login' => 'logowanie (Fortify, throttle)',
    'GET forgot-password' => 'formularz resetu hasła (Fortify)',
    'POST forgot-password' => 'wysłanie linku resetu hasła (Fortify)',
    'GET reset-password/{token}' => 'formularz nowego hasła z tokenem (Fortify)',
    'POST reset-password' => 'zapis nowego hasła z tokenem (Fortify)',
    'GET two-factor-challenge' => 'drugi krok logowania przed sesją (Fortify)',
    'POST two-factor-challenge' => 'weryfikacja TOTP przed sesją (Fortify)',
    'GET passkeys/login/options' => 'opcje WebAuthn przed logowaniem',
    'POST passkeys/login' => 'logowanie passkey',
    'GET .well-known/passkey-endpoints' => 'publiczny opis endpointów passkey (W3C)',
    'GET health/ready' => 'readiness dla monitoringu i deployu, bez danych',
    'GET sitemap.xml' => 'SEO',
    'GET robots.txt' => 'SEO',
    'GET llms.txt' => 'SEO (AI/LLM index)',
    'GET /' => 'strona główna',
    'GET articles' => 'publiczna lista artykułów',
    'GET articles/{slug}' => 'publiczny artykuł',
    'POST contact-messages' => 'publiczny formularz kontaktowy (throttle, honeypot)',
    'GET {slug}' => 'publiczna strona CMS',
    'GET shared-preview/{token}' => 'podgląd szkicu dla klienta: tajny, wygasający i odwoływalny token (throttle, noindex)',
    'PATCH settings/locale' => 'preferencja języka panelu w sesji, także przed logowaniem',
];

/*
 * Trasy publiczne zależne od konfiguracji: rejestracja (APP_REGISTRATION_ENABLED), alias domyślnego
 * języka i prefiks {locale} dla dodatkowych języków. Mogą nie istnieć, więc druga bramka ich nie wymaga.
 */
$routesAuthPublicOptional = static function (): array {
    $routesAuthDefaultLocale = app(LocalizationConfig::class)->getPublicDefault();

    return [
        'GET register' => 'rejestracja, gdy włączona',
        'POST register' => 'rejestracja, gdy włączona',
        "GET {$routesAuthDefaultLocale}" => 'przekierowanie 301 aliasu domyślnego języka',
        "GET {$routesAuthDefaultLocale}/{path}" => 'przekierowanie 301 aliasu domyślnego języka',
        'GET {locale}' => 'strona główna w dodatkowym języku',
        'GET {locale}/articles' => 'lista artykułów w dodatkowym języku',
        'GET {locale}/articles/{slug}' => 'artykuł w dodatkowym języku',
        'POST {locale}/contact-messages' => 'formularz kontaktowy w dodatkowym języku',
        'GET {locale}/{slug}' => 'strona CMS w dodatkowym języku',
        'GET {locale}/login' => 'logowanie w dodatkowym języku',
        'POST {locale}/login' => 'logowanie w dodatkowym języku',
        'GET {locale}/forgot-password' => 'reset hasła w dodatkowym języku',
        'POST {locale}/forgot-password' => 'reset hasła w dodatkowym języku',
        'GET {locale}/reset-password/{token}' => 'reset hasła w dodatkowym języku',
        'POST {locale}/reset-password' => 'reset hasła w dodatkowym języku',
        'GET {locale}/register' => 'rejestracja w dodatkowym języku, gdy włączona',
        'POST {locale}/register' => 'rejestracja w dodatkowym języku, gdy włączona',
        'GET {locale}/two-factor-challenge' => 'drugi krok logowania w dodatkowym języku',
        'POST {locale}/two-factor-challenge' => 'drugi krok logowania w dodatkowym języku',
    ];
};

// Trasy frameworka i paczek, które nie należą do aplikacji.
// Cały segment ścieżki: 'up' pomija /up, ale nie /uploads.
$routesAuthIgnoredPrefixes = ['up', 'sanctum', 'storage', 'telescope', 'horizon', 'livewire', 'broadcasting'];

$routesAuthKey = static fn (RoutingRoute $route): string => implode('|', array_values(array_diff($route->methods(), ['HEAD']))).' '.$route->uri();

$routesAuthRequired = static function (RoutingRoute $route): bool {
    foreach ($route->gatherMiddleware() as $middleware) {
        if (is_string($middleware) && (str_starts_with($middleware, 'auth') || str_ends_with($middleware, '\\Authenticate'))) {
            return true;
        }
    }

    return false;
};

$routesAuthApplicationRoutes = static fn (): array => array_values(array_filter(
    Route::getRoutes()->getRoutes(),
    static fn (RoutingRoute $route): bool => ! str_starts_with($route->uri(), '_')
        && array_filter(
            $routesAuthIgnoredPrefixes,
            static fn (string $prefix): bool => $route->uri() === $prefix || str_starts_with($route->uri(), $prefix.'/'),
        ) === [],
));

it('wymaga uwierzytelnienia na każdej trasie poza listą publicznych', function () use ($routesAuthPublic, $routesAuthPublicOptional, $routesAuthKey, $routesAuthRequired, $routesAuthApplicationRoutes): void {
    $unprotected = array_values(array_filter(
        array_map(static fn (RoutingRoute $route): ?string => $routesAuthRequired($route) ? null : $routesAuthKey($route), $routesAuthApplicationRoutes()),
        static fn (?string $key): bool => $key !== null && ! array_key_exists($key, $routesAuthPublic + $routesAuthPublicOptional()),
    ));

    expect($unprotected)->toBe([], 'Trasa bez uwierzytelnienia. Dodaj middleware auth albo wpis w $routesAuthPublic z powodem.');
});

it('lista tras publicznych zawiera tylko istniejące trasy bez uwierzytelnienia', function () use ($routesAuthPublic, $routesAuthKey, $routesAuthRequired, $routesAuthApplicationRoutes): void {
    $public = array_map(static fn (RoutingRoute $route): string => $routesAuthKey($route), array_filter(
        $routesAuthApplicationRoutes(),
        static fn (RoutingRoute $route): bool => ! $routesAuthRequired($route),
    ));

    expect(array_values(array_diff(array_keys($routesAuthPublic), $public)))->toBe([], 'Usuń z $routesAuthPublic trasy, których nie ma albo które wymagają już uwierzytelnienia.');
});
