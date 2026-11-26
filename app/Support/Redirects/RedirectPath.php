<?php

namespace App\Support\Redirects;

use App\Services\Localization\LocalizationConfig;
use App\Services\Localization\LocalizationManager;
use Illuminate\Http\Request;

/**
 * Normalization of managed redirect paths. A source is matched by its path
 * only: decoded, lowercase, with one leading and no trailing slash and
 * without query or fragment, so `/Oferta/`, `/oferta?x=1` and a full URL of
 * the former site (`https://old.example/oferta`) are the same source.
 */
final class RedirectPath
{
    /**
     * Normalized source path of a path, a path with a query or a full URL.
     */
    public static function source(string $value): string
    {
        $value = trim($value);

        if (preg_match('#^https?://#i', $value) === 1) {
            $value = (string) parse_url($value, PHP_URL_PATH);
        }

        $path = (string) preg_replace('/[?#].*$/s', '', $value);
        $path = mb_strtolower(rawurldecode($path));
        $path = '/'.trim((string) preg_replace('#/+#', '/', $path), '/');

        return $path;
    }

    /**
     * Stored form of a target: relative targets lose duplicate and trailing
     * slashes of the path (query and fragment are kept as entered); absolute
     * and protocol-relative (`//host`, rejected by validation) URLs are kept
     * as entered.
     */
    public static function target(string $value): string
    {
        $value = trim($value);

        if (! str_starts_with($value, '/') || str_starts_with($value, '//')) {
            return $value;
        }

        $suffixStart = strcspn($value, '?#');
        $path = substr($value, 0, $suffixStart);
        $path = '/'.trim((string) preg_replace('#/+#', '/', $path), '/');

        return $path.substr($value, $suffixStart);
    }

    /**
     * Whether the value is a relative path (`/…`, not `//host`) or an
     * absolute http(s) URL with a host.
     */
    public static function isValidTarget(string $value): bool
    {
        if (preg_match('/[\s\\\\\p{Cc}]/u', $value) === 1) {
            return false;
        }

        if (str_starts_with($value, '/')) {
            return ! str_starts_with($value, '//');
        }

        return preg_match('#^https?://#i', $value) === 1
            && filter_var($value, FILTER_VALIDATE_URL) !== false
            && is_string(parse_url($value, PHP_URL_HOST));
    }

    /**
     * Normalized path of a target that points at this site (relative or an
     * absolute URL on the APP_URL host); null for external targets.
     */
    public static function localTargetPath(string $target): ?string
    {
        if (str_starts_with($target, '/')) {
            return self::source($target);
        }

        $host = parse_url($target, PHP_URL_HOST);
        $appHost = parse_url((string) config('app.url'), PHP_URL_HOST);

        if (! is_string($host) || ! is_string($appHost) || strcasecmp($host, $appHost) !== 0) {
            return null;
        }

        return self::source($target);
    }

    /**
     * Target with the query string of the request appended, unless the
     * target has its own query (a fragment stays last).
     */
    public static function withQuery(string $target, ?string $query): string
    {
        if ($query === null || $query === '' || str_contains($target, '?')) {
            return $target;
        }

        $fragmentStart = strcspn($target, '#');

        return substr($target, 0, $fragmentStart).'?'.$query.substr($target, $fragmentStart);
    }

    /**
     * Whether a normalized path belongs to the panel or sign-in and may
     * therefore never be redirected (also behind a public locale prefix).
     */
    public static function isReserved(string $path): bool
    {
        $segments = explode('/', ltrim($path, '/'));

        if ($segments[0] !== '' && app(LocalizationConfig::class)->isPublicLocale($segments[0])) {
            array_shift($segments);
        }

        if (in_array($segments[0] ?? '', ['admin', 'settings', 'up'], true)) {
            return true;
        }

        return app(LocalizationManager::class)->isAuthPath(Request::create($path));
    }
}
