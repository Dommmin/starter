<?php

namespace App\Repositories\Redirects;

use App\Models\Redirect;
use App\Support\Redirects\RedirectPath;
use Illuminate\Support\Facades\Cache;
use Throwable;

/**
 * Queries of managed redirects. Active redirects are kept as one cached map
 * keyed by the normalized source path; Redirect drops it after every save
 * or delete. Hit counters are written outside the cache and the model (no
 * `updated_at` bump, so counting never causes an edit conflict).
 *
 * @phpstan-type RedirectMap array<string, array{id: int, target: string, status: int}>
 */
class RedirectRepository
{
    private const string CACHE_KEY = 'redirects.map';

    /** @var RedirectMap|null */
    private ?array $map = null;

    /**
     * Active redirect of the normalized source path.
     *
     * @return array{id: int, target: string, status: int}|null
     */
    public function findActive(string $sourcePath): ?array
    {
        return $this->map()[$sourcePath] ?? null;
    }

    public function recordHit(int $id): void
    {
        Redirect::query()->whereKey($id)->toBase()->increment('hits', 1, ['last_hit_at' => now()]);
    }

    /**
     * Source paths and local target paths of every redirect (active or not,
     * except `$ignoreId`), used to reject chains and loops.
     *
     * @return array{sources: list<string>, targets: list<string>}
     */
    public function chainEndpoints(?int $ignoreId = null): array
    {
        $sources = [];
        $targets = [];

        foreach (Redirect::query()->when($ignoreId !== null, fn ($query) => $query->whereKeyNot($ignoreId))->get(['id', 'source_path', 'target']) as $redirect) {
            $sources[] = $redirect->source_path;
            $targetPath = RedirectPath::localTargetPath($redirect->target);

            if ($targetPath !== null) {
                $targets[] = $targetPath;
            }
        }

        return ['sources' => $sources, 'targets' => $targets];
    }

    /**
     * Drop the cached map; the next lookup rebuilds it from the database.
     */
    public function forget(): void
    {
        $this->map = null;
        Cache::forget(self::CACHE_KEY);
    }

    /**
     * @return RedirectMap
     */
    private function map(): array
    {
        if ($this->map !== null) {
            return $this->map;
        }

        try {
            /** @var RedirectMap $map */
            $map = Cache::rememberForever(self::CACHE_KEY, fn (): array => $this->load());
        } catch (Throwable $exception) {
            /*
             * An unavailable database or cache must not take public pages
             * down: the failure is reported and this request is served
             * without managed redirects.
             */
            report($exception);
            $map = [];
        }

        return $this->map = $map;
    }

    /**
     * @return RedirectMap
     */
    private function load(): array
    {
        $map = [];

        foreach (Redirect::query()->where('is_active', true)->get(['id', 'source_path', 'target', 'status_code']) as $redirect) {
            $map[$redirect->source_path] = [
                'id' => $redirect->id,
                'target' => $redirect->target,
                'status' => $redirect->status_code->value,
            ];
        }

        return $map;
    }
}
