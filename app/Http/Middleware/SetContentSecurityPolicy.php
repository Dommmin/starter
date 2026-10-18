<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Vite;
use Symfony\Component\HttpFoundation\Response;

/**
 * Sends the Content Security Policy from config/csp.php on every response.
 * The per-request nonce generated for Vite covers the inline scripts and
 * styles of the root template, so the policy never needs 'unsafe-inline'.
 * In Report-Only mode (default) violations are measured, not enforced.
 */
class SetContentSecurityPolicy
{
    /**
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $nonce = Vite::useCspNonce();

        $response = $next($request);

        // A response with its own (stricter) policy, such as a media download, keeps it.
        if ($response->headers->has('Content-Security-Policy')) {
            return $response;
        }

        $header = config('csp.report_only')
            ? 'Content-Security-Policy-Report-Only'
            : 'Content-Security-Policy';

        $response->headers->set($header, $this->policy($nonce));

        return $response;
    }

    protected function policy(string $nonce): string
    {
        $dev = $this->devServerOrigins();
        $analytics = $this->plausibleOrigins();

        $directives = [
            'default-src' => ["'self'"],
            'script-src' => ["'self'", "'nonce-{$nonce}'", ...$this->configuredOrigins('csp.script_src'), ...$analytics, ...$dev['http']],
            'style-src' => ["'self'", "'nonce-{$nonce}'", ...$dev['http']],
            'img-src' => ["'self'", 'data:', 'blob:', ...$dev['http']],
            'font-src' => ["'self'", ...$dev['http']],
            'connect-src' => ["'self'", ...$this->configuredOrigins('csp.connect_src'), ...$analytics, ...$dev['http'], ...$dev['ws']],
            'object-src' => ["'none'"],
            'base-uri' => ["'self'"],
            'frame-ancestors' => ["'none'"],
            'form-action' => ["'self'"],
        ];

        $reportUri = config('csp.report_uri');
        if (is_string($reportUri) && $reportUri !== '') {
            $directives['report-uri'] = [$reportUri];
        }

        return collect($directives)
            ->map(fn (array $sources, string $name): string => $name.' '.implode(' ', array_unique($sources)))
            ->implode('; ');
    }

    /**
     * @return list<string>
     */
    protected function configuredOrigins(string $key): array
    {
        $value = config($key);

        return is_string($value) ? array_values(array_filter(explode(' ', $value))) : [];
    }

    /**
     * Origin of the configured Plausible script (services.plausible), which
     * also receives its events; the script loads only after the analytics
     * consent, but the policy allows it whenever it is configured.
     *
     * @return list<string>
     */
    protected function plausibleOrigins(): array
    {
        $domain = config('services.plausible.domain');
        $scriptUrl = config('services.plausible.script_url');

        if (! is_string($domain) || $domain === '' || ! is_string($scriptUrl) || ! str_starts_with($scriptUrl, 'https://')) {
            return [];
        }

        $host = parse_url($scriptUrl, PHP_URL_HOST);
        $port = parse_url($scriptUrl, PHP_URL_PORT);

        return is_string($host) && $host !== ''
            ? ['https://'.$host.(is_int($port) ? ':'.$port : '')]
            : [];
    }

    /**
     * The Vite dev server (assets, React Refresh, HMR websocket) is a separate
     * origin only while public/hot exists, i.e. in local development.
     *
     * @return array{http: list<string>, ws: list<string>}
     */
    protected function devServerOrigins(): array
    {
        if (! Vite::isRunningHot()) {
            return ['http' => [], 'ws' => []];
        }

        $origin = rtrim(trim((string) file_get_contents(Vite::hotFile())), '/');

        if (! str_starts_with($origin, 'http')) {
            return ['http' => [], 'ws' => []];
        }

        return ['http' => [$origin], 'ws' => ['ws'.substr($origin, 4)]];
    }
}
