<?php

namespace App\Support\Env;

use RuntimeException;

/**
 * Minimal editor of a dotenv file for `app:init-project`: it changes only
 * the given keys (replacing their first `KEY=` line or appending a missing
 * key) and keeps every other line and comment byte for byte. Values are
 * written unquoted, so callers pass only simple validated tokens.
 */
class EnvFileEditor
{
    private readonly string $path;

    public function __construct(?string $path = null)
    {
        $this->path = $path ?? app()->environmentFilePath();
    }

    public function path(): string
    {
        return $this->path;
    }

    public function exists(): bool
    {
        return is_file($this->path);
    }

    /**
     * Current value of the key, or null when the key is absent.
     */
    public function get(string $key): ?string
    {
        if (! $this->exists()) {
            return null;
        }

        if (preg_match('/^'.preg_quote($key, '/').'=(.*)$/m', $this->contents(), $matches) !== 1) {
            return null;
        }

        return trim(trim($matches[1]), '"\'');
    }

    /**
     * Keys whose value differs from the requested one.
     *
     * @param  array<string, string>  $values
     * @return array<string, array{old: string|null, new: string}>
     */
    public function changes(array $values): array
    {
        $changes = [];

        foreach ($values as $key => $value) {
            $current = $this->get($key);

            if ($current !== $value) {
                $changes[$key] = ['old' => $current, 'new' => $value];
            }
        }

        return $changes;
    }

    /**
     * Copy the file next to itself as `.backup-YYYYmmddHHMMSS`, readable only
     * by the owner. An existing backup is never overwritten: a taken name gets
     * a `-2`, `-3`, … suffix.
     */
    public function backup(): string
    {
        $basePath = $this->path.'.backup-'.now()->format('YmdHis');
        $backupPath = $basePath;

        for ($attempt = 2; file_exists($backupPath); $attempt++) {
            $backupPath = $basePath.'-'.$attempt;
        }

        if (! copy($this->path, $backupPath) || ! chmod($backupPath, 0600)) {
            throw new RuntimeException('The environment file backup could not be created.');
        }

        return $backupPath;
    }

    /**
     * Set the given keys; every other line stays unchanged.
     *
     * @param  array<string, string>  $values
     */
    public function write(array $values): void
    {
        $contents = $this->contents();

        foreach ($values as $key => $value) {
            if (preg_match('/[\s"\'#\\\\$]/', $value) === 1) {
                throw new RuntimeException("Unsupported characters in the value of [{$key}].");
            }

            $pattern = '/^'.preg_quote($key, '/').'=.*$/m';

            if (preg_match($pattern, $contents) === 1) {
                $contents = (string) preg_replace($pattern, $key.'='.$value, $contents, 1);

                continue;
            }

            if ($contents !== '' && ! str_ends_with($contents, "\n")) {
                $contents .= "\n";
            }

            $contents .= $key.'='.$value."\n";
        }

        if (file_put_contents($this->path, $contents, LOCK_EX) === false) {
            throw new RuntimeException('The environment file could not be written.');
        }
    }

    private function contents(): string
    {
        $contents = file_get_contents($this->path);

        if ($contents === false) {
            throw new RuntimeException('The environment file could not be read.');
        }

        return $contents;
    }
}
