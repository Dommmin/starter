<?php

declare(strict_types=1);

/*
 * Managed shared/.env (doc 10, "Konfiguracja produkcji w GitHub Environment").
 *
 * render TEMPLATE OPTIONAL_KEYS
 *     Runs in the deploy job. Builds the .env from the reviewed template
 *     (deploy/env/production.env.example) and the GitHub Environment
 *     variables/secrets passed as JSON in MANAGED_ENV_VARS and
 *     MANAGED_ENV_SECRETS. Only template keys and keys listed in
 *     OPTIONAL_KEYS are written; a template value with a __PLACEHOLDER__
 *     must be provided; secret-looking keys must come from secrets.
 *     Prints the file to stdout, never a value to stderr.
 *
 * compare CURRENT NEXT
 *     Runs on the server. Prints only key names that were added, removed
 *     or changed between two .env files.
 */

use Dotenv\Dotenv;

require __DIR__.'/../../vendor/autoload.php';

const SECRET_KEY_PATTERN = '/(_KEY|_PASSWORD|_SECRET|_TOKEN)\z/';
const PLACEHOLDER_PATTERN = '/__[A-Z0-9_]+__/';
const KEY_PATTERN = '/\A[A-Z][A-Z0-9_]*\z/';

/**
 * @param  list<string>  $arguments
 */
function main(array $arguments): int
{
    try {
        return match ($arguments[1] ?? '') {
            'render' => render(argument($arguments, 2), argument($arguments, 3)),
            'compare' => compare(argument($arguments, 2), argument($arguments, 3)),
            default => throw new InvalidArgumentException('Usage: managed-env.php render TEMPLATE OPTIONAL_KEYS | compare CURRENT NEXT'),
        };
    } catch (Throwable $exception) {
        fwrite(STDERR, 'managed-env: '.$exception->getMessage().PHP_EOL);

        return 1;
    }
}

/**
 * @param  list<string>  $arguments
 */
function argument(array $arguments, int $index): string
{
    $value = $arguments[$index] ?? '';

    if ($value === '') {
        throw new InvalidArgumentException('Missing argument '.$index.'.');
    }

    return $value;
}

function readFileOrFail(string $path): string
{
    $content = is_file($path) ? file_get_contents($path) : false;

    if ($content === false) {
        throw new RuntimeException('Cannot read '.$path.'.');
    }

    return $content;
}

/**
 * @return array<string, string>
 */
function environmentJson(string $name): array
{
    $json = getenv($name);
    $decoded = json_decode($json === false || $json === '' ? '{}' : $json, true, flags: JSON_THROW_ON_ERROR);

    if (! is_array($decoded)) {
        throw new RuntimeException($name.' must be a JSON object.');
    }

    $values = [];

    foreach ($decoded as $key => $value) {
        if (is_string($key) && is_string($value)) {
            $values[$key] = $value;
        }
    }

    return $values;
}

/**
 * Template lines in order: key => raw line.
 *
 * @return array<string, string>
 */
function templateLines(string $content): array
{
    $lines = [];

    foreach (preg_split('/\R/', $content) ?: [] as $line) {
        if (preg_match('/\A([A-Z][A-Z0-9_]*)=/', $line, $match) === 1) {
            $lines[$match[1]] = $line;
        }
    }

    return $lines;
}

/**
 * @return list<string>
 */
function optionalKeys(string $content): array
{
    $keys = [];

    foreach (preg_split('/\R/', $content) ?: [] as $line) {
        $line = trim($line);

        if ($line === '' || str_starts_with($line, '#')) {
            continue;
        }

        if (preg_match(KEY_PATTERN, $line) !== 1) {
            throw new RuntimeException('Invalid key in the optional key list: '.$line.'.');
        }

        $keys[] = $line;
    }

    return $keys;
}

/**
 * A value as a .env assignment that phpdotenv reads back unchanged: plain
 * when safe, single-quoted (literal, no interpolation) otherwise.
 */
function assignment(string $key, string $value): string
{
    if (preg_match('/\A[A-Za-z0-9_.,:\/@+-]+\z/', $value) === 1) {
        return $key.'='.$value;
    }

    if (preg_match('/[\'\r\n\0]/', $value) === 1) {
        throw new RuntimeException($key.' contains a quote or a line break, which the managed .env does not support.');
    }

    return $key."='".$value."'";
}

function render(string $templatePath, string $optionalKeysPath): int
{
    $template = templateLines(readFileOrFail($templatePath));
    $optional = optionalKeys(readFileOrFail($optionalKeysPath));
    $variables = environmentJson('MANAGED_ENV_VARS');
    $secrets = environmentJson('MANAGED_ENV_SECRETS');
    $allowed = array_merge(array_keys($template), $optional);

    $provided = [];
    $errors = [];

    foreach ($allowed as $key) {
        $inVariables = array_key_exists($key, $variables);
        $inSecrets = array_key_exists($key, $secrets);

        if ($inVariables && $inSecrets) {
            $errors[] = $key.' is set both as a variable and as a secret.';
        } elseif ($inVariables && preg_match(SECRET_KEY_PATTERN, $key) === 1) {
            $errors[] = $key.' must be an Environment secret, not a variable.';
        } elseif ($inVariables || $inSecrets) {
            $provided[$key] = $inSecrets ? $secrets[$key] : $variables[$key];
        }
    }

    $lines = ['# Generated by the deploy workflow from deploy/env/production.env.example', '# and the GitHub Environment. Manual edits are replaced on the next deploy.'];

    foreach ($template as $key => $line) {
        if (array_key_exists($key, $provided)) {
            $lines[] = assignment($key, $provided[$key]);
        } elseif (preg_match(PLACEHOLDER_PATTERN, $line) === 1) {
            $errors[] = $key.' is required (the template has a placeholder).';
        } else {
            $lines[] = $line;
        }
    }

    foreach ($optional as $key) {
        if (array_key_exists($key, $provided) && ! array_key_exists($key, $template)) {
            $lines[] = assignment($key, $provided[$key]);
        }
    }

    if ($errors !== []) {
        throw new RuntimeException(PHP_EOL.'  '.implode(PHP_EOL.'  ', $errors));
    }

    $content = implode(PHP_EOL, $lines).PHP_EOL;
    $parsed = Dotenv::parse($content);

    foreach ($provided as $key => $value) {
        if (($parsed[$key] ?? null) !== $value) {
            throw new RuntimeException($key.' does not survive the .env round trip.');
        }
    }

    fwrite(STDOUT, $content);

    return 0;
}

function compare(string $currentPath, string $nextPath): int
{
    $current = is_file($currentPath) ? Dotenv::parse(readFileOrFail($currentPath)) : [];
    $next = Dotenv::parse(readFileOrFail($nextPath));

    $added = array_keys(array_diff_key($next, $current));
    $removed = array_keys(array_diff_key($current, $next));
    $changed = array_keys(array_filter(
        array_intersect_key($next, $current),
        fn (?string $value, string $key): bool => $value !== $current[$key],
        ARRAY_FILTER_USE_BOTH,
    ));

    if ($added === [] && $removed === [] && $changed === []) {
        fwrite(STDOUT, 'shared/.env: no changes'.PHP_EOL);

        return 0;
    }

    foreach (['added' => $added, 'removed' => $removed, 'changed' => $changed] as $label => $keys) {
        if ($keys !== []) {
            sort($keys);
            fwrite(STDOUT, sprintf('shared/.env %s: %s', $label, implode(', ', $keys)).PHP_EOL);
        }
    }

    return 0;
}

exit(main($argv));
