<?php

namespace App\Support\TypeScript;

use RuntimeException;
use Spatie\TypeScriptTransformer\Formatters\Formatter;
use Symfony\Component\Process\Process;

/**
 * Formats generated TypeScript with the project's own formatter (`vp fmt`,
 * configured in vite.config.ts) so generated files pass `npm run check`
 * without an extra dependency. Used only by the dev-only transformer provider.
 */
class VitePlusFormatter implements Formatter
{
    /**
     * @param  array<string>  $files
     */
    public function format(array $files): void
    {
        if ($files === []) {
            return;
        }

        $binary = base_path('node_modules/.bin/vp');

        if (! is_file($binary)) {
            throw new RuntimeException('Cannot format generated TypeScript: run `npm ci` first (missing node_modules/.bin/vp).');
        }

        $process = new Process([$binary, 'fmt', '--write', ...$files], base_path());
        $process->mustRun();
    }
}
