<?php

namespace App\Actions\Redirects;

use App\Models\NotFoundLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Throwable;

/**
 * Records a 404 hit for non-noise public paths so administrators can spot
 * broken links and convert them into 301 redirects.
 */
class RecordNotFoundHit
{
    /** Anyone can request random URLs, so the table must not grow without end. */
    public const int MAX_PATHS = 1000;

    private const array NOISE_EXTENSIONS = [
        '.php', '.env', '.git', '.sql', '.bak', '.zip', '.tar', '.gz',
        '.png', '.jpg', '.jpeg', '.gif', '.webp', '.svg', '.ico', '.css', '.js', '.map', '.woff', '.woff2',
    ];

    private const array NOISE_PREFIXES = [
        '/admin', '/api', '/shared-preview', '/_', '/wp-', '/wordpress', '/xmlrpc', '/.well-known', '/.env', '/.git',
    ];

    public function handle(Request $request): void
    {
        if (! $request->isMethod('GET') && ! $request->isMethod('HEAD')) {
            return;
        }

        $path = '/'.ltrim($request->path(), '/');
        if ($this->isNoise($path)) {
            return;
        }

        $normalized = Str::limit($path, 255, '');
        $referer = $request->headers->get('referer');
        // Query strings and fragments can carry personal data or tokens.
        $lastReferer = is_string($referer) && $referer !== '' ? Str::limit(Str::before(Str::before($referer, '?'), '#'), 500, '') : null;
        $now = now();

        try {
            DB::transaction(function () use ($normalized, $lastReferer, $now): void {
                $existing = NotFoundLog::query()->where('path', $normalized)->first();

                if ($existing !== null) {
                    $existing->increment('hits');
                    $existing->update([
                        'last_referer' => $lastReferer ?? $existing->last_referer,
                        'last_seen_at' => $now,
                    ]);

                    return;
                }

                if (NotFoundLog::query()->count() >= self::MAX_PATHS) {
                    return;
                }

                NotFoundLog::query()->create([
                    'path' => $normalized,
                    'hits' => 1,
                    'last_referer' => $lastReferer,
                    'last_seen_at' => $now,
                ]);
            });
        } catch (Throwable) {
            // Never break the 404 response if logging fails.
        }
    }

    private function isNoise(string $path): bool
    {
        $lower = strtolower($path);

        foreach (self::NOISE_PREFIXES as $prefix) {
            if (str_starts_with($lower, $prefix)) {
                return true;
            }
        }

        foreach (self::NOISE_EXTENSIONS as $extension) {
            if (str_ends_with($lower, $extension)) {
                return true;
            }
        }

        return false;
    }
}
