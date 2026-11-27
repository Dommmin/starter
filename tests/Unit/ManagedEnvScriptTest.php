<?php

use Dotenv\Dotenv;
use Symfony\Component\Process\Process;

/**
 * Required template values as a GitHub Environment would provide them.
 *
 * @return array{vars: array<string, string>, secrets: array<string, string>}
 */
function managedEnvInput(): array
{
    return [
        'vars' => [
            'APP_NAME' => 'Synthetic Site',
            'APP_URL' => 'https://example.test',
            'MAIL_EHLO_DOMAIN' => 'example.test',
            'MAIL_FROM_ADDRESS' => 'hello@example.test',
            'OPS_ALERT_EMAIL' => 'ops@example.test',
            'DEPLOY_HOST' => 'example.test',
        ],
        'secrets' => [
            'APP_KEY' => 'base64:synthetic-app-key',
            'DB_PASSWORD' => 'synthetic $db #password',
            'HEALTH_TOKEN' => 'synthetic-health-token',
            'DEPLOY_SSH_KEY' => 'synthetic-ssh-key',
        ],
    ];
}

/**
 * @param  array{vars: array<string, string>, secrets: array<string, string>}  $input
 */
function renderManagedEnv(array $input): Process
{
    $process = new Process(
        ['php', 'scripts/deploy/managed-env.php', 'render', 'deploy/env/production.env.example', 'deploy/env/optional.keys'],
        dirname(__DIR__, 2),
        [
            'MANAGED_ENV_VARS' => json_encode($input['vars'], JSON_THROW_ON_ERROR),
            'MANAGED_ENV_SECRETS' => json_encode($input['secrets'], JSON_THROW_ON_ERROR),
        ],
    );
    $process->run();

    return $process;
}

test('the rendered env keeps template defaults, fills placeholders and adds only allowed keys', function () {
    $input = managedEnvInput();
    $input['vars']['AI_FEATURES_ENABLED'] = 'true';
    $input['vars']['APP_PUBLIC_DEFAULT'] = 'en';
    $input['vars']['UNRELATED_SETTING'] = 'ignored';

    $process = renderManagedEnv($input);

    expect($process->getExitCode())->toBe(0, $process->getErrorOutput());
    $output = $process->getOutput();
    $env = Dotenv::parse($output);

    expect($env)
        ->toMatchArray([
            'APP_ENV' => 'production',
            'APP_DEBUG' => 'false',
            'APP_NAME' => 'Synthetic Site',
            'APP_PUBLIC_DEFAULT' => 'en',
            'APP_KEY' => 'base64:synthetic-app-key',
            'DB_PASSWORD' => 'synthetic $db #password',
            'MAIL_FROM_NAME' => 'Synthetic Site',
            'AI_FEATURES_ENABLED' => 'true',
        ])
        ->not->toHaveKeys(['UNRELATED_SETTING', 'DEPLOY_HOST', 'DEPLOY_SSH_KEY'])
        ->and($output)->not->toContain('__');
});

test('a missing placeholder value fails without printing any value', function () {
    $input = managedEnvInput();
    unset($input['secrets']['APP_KEY']);

    $process = renderManagedEnv($input);

    expect($process->getExitCode())->toBe(1)
        ->and($process->getOutput())->toBe('')
        ->and($process->getErrorOutput())->toContain('APP_KEY is required')
        ->not->toContain('synthetic');
});

test('secret-looking keys must be secrets and a key may not be set twice', function () {
    $input = managedEnvInput();
    $input['vars']['MAIL_PASSWORD'] = 'synthetic-mail-password';
    $input['vars']['HEALTH_TOKEN'] = 'synthetic-health-token';

    $process = renderManagedEnv($input);

    expect($process->getExitCode())->toBe(1)
        ->and($process->getOutput())->toBe('')
        ->and($process->getErrorOutput())
        ->toContain('MAIL_PASSWORD must be an Environment secret')
        ->toContain('HEALTH_TOKEN is set both as a variable and as a secret')
        ->not->toContain('synthetic');
});

test('a value with a quote or a line break is refused', function (string $value) {
    $input = managedEnvInput();
    $input['vars']['APP_NAME'] = $value;

    $process = renderManagedEnv($input);

    expect($process->getExitCode())->toBe(1)
        ->and($process->getErrorOutput())->toContain('APP_NAME contains a quote or a line break');
})->with([
    'single quote' => ["Synthetic's Site"],
    'line break' => ["Synthetic\nSite"],
]);

test('the comparison reports key names only', function () {
    $directory = sys_get_temp_dir().'/managed-env-'.bin2hex(random_bytes(4));
    mkdir($directory);
    file_put_contents($directory.'/current', "APP_NAME=Old\nDB_PASSWORD=synthetic-old\nLEGACY_FLAG=true\nAPP_ENV=production\n");
    file_put_contents($directory.'/next', "APP_NAME=Old\nDB_PASSWORD=synthetic-new\nAPP_ENV=production\nAI_FEATURES_ENABLED=true\n");

    $process = new Process(['php', 'scripts/deploy/managed-env.php', 'compare', $directory.'/current', $directory.'/next'], dirname(__DIR__, 2));
    $process->run();

    expect($process->getExitCode())->toBe(0, $process->getErrorOutput())
        ->and($process->getOutput())->toBe(implode(PHP_EOL, [
            'shared/.env added: AI_FEATURES_ENABLED',
            'shared/.env removed: LEGACY_FLAG',
            'shared/.env changed: DB_PASSWORD',
        ]).PHP_EOL)
        ->not->toContain('synthetic');

    array_map(unlink(...), glob($directory.'/*') ?: []);
    rmdir($directory);
});
