<?php

declare(strict_types=1);

namespace Deployer;

require 'recipe/laravel.php';

/** @return non-empty-string */
function deploymentEnvironment(string $name): string
{
    $value = getenv($name);

    if ($value === false || $value === '') {
        throw new \RuntimeException(sprintf('Brak wymaganej zmiennej środowiskowej %s.', $name));
    }

    return $value;
}

function optionalEnvironment(string $name, string $default): string
{
    $value = getenv($name);

    return $value === false || $value === '' ? $default : $value;
}

$environment = deploymentEnvironment('DEPLOY_ENVIRONMENT');

host($environment)
    ->setHostname(deploymentEnvironment('DEPLOY_HOST'))
    ->setRemoteUser(deploymentEnvironment('DEPLOY_USER'))
    ->set('deploy_path', deploymentEnvironment('DEPLOY_PATH'));

set('application', 'starter');
set('keep_releases', 5);
set('shared_files', ['.env']);
set('shared_dirs', ['storage']);
set('writable_dirs', ['bootstrap/cache', 'storage']);
set('writable_mode', 'acl');
set('writable_use_sudo', false);
set('artifact_path', deploymentEnvironment('DEPLOY_ARTIFACT'));
set('artifact_sha256', deploymentEnvironment('DEPLOY_ARTIFACT_SHA256'));

/*
 * Post-switch readiness (ADR-022 step 6): three attempts, 5 s timeout each,
 * 5 s apart. DEPLOY_SMOKE_HOST is the public host name (APP_URL host); the
 * request is pinned to the local Nginx with --resolve so TrustHosts and TLS
 * behave as for real traffic. Without it the check falls back to plain HTTP
 * on 127.0.0.1 (only valid when that is the configured host).
 */
set('smoke_attempts', 3);
set('smoke_interval_seconds', 5);
set('smoke_timeout_seconds', 5);
set('smoke_command', function (): string {
    $host = optionalEnvironment('DEPLOY_SMOKE_HOST', '');
    $curl = sprintf('curl --fail --silent --show-error --max-time %d', (int) get('smoke_timeout_seconds'));

    if ($host === '') {
        return $curl.' http://127.0.0.1/health/ready > /dev/null';
    }

    if (preg_match('/\A[a-z0-9.-]+\z/i', $host) !== 1) {
        throw new \RuntimeException('DEPLOY_SMOKE_HOST musi być nazwą hosta.');
    }

    return sprintf('%1$s --resolve %2$s:443:127.0.0.1 https://%2$s/health/ready > /dev/null', $curl, $host);
});

task('deploy:update_code', function (): void {
    $artifactPath = get('artifact_path');

    if (! is_file($artifactPath)) {
        throw new \RuntimeException(sprintf('Brak artefaktu release: %s.', $artifactPath));
    }

    upload($artifactPath, '{{release_path}}/release.tar.gz');
    run('test "$(sha256sum {{release_path}}/release.tar.gz | cut -d " " -f 1)" = "{{artifact_sha256}}"');
    run('tar -xzf {{release_path}}/release.tar.gz -C {{release_path}} --no-same-owner');
    run('rm {{release_path}}/release.tar.gz');
    // Release ID for logs (config/ops.php reads it during config:cache).
    run('printf "%s\n" "{{release_name}}" > {{release_path}}/RELEASE');
});

task('deploy:check_blocked', function (): void {
    if (test('[ -f {{deploy_path}}/.dep/deploy_blocked ]')) {
        throw new \RuntimeException('Deploye zablokowane po nieudanym automatycznym rollbacku (.dep/deploy_blocked) — decyzja operatora.');
    }
});

task('deploy:vendors', function (): void {
    run('test -f {{release_path}}/vendor/autoload.php');
});

task('deploy:migrate', function (): void {
    if (getenv('DEPLOY_ALLOW_MIGRATIONS') !== 'true') {
        writeln('<comment>Migracje pominięte: DEPLOY_ALLOW_MIGRATIONS=true jest wymagane.</comment>');

        return;
    }

    run('cd {{release_path}} && php artisan migrate --force --no-interaction');
});

/*
 * Pre-switch checks of the candidate (the active release keeps serving):
 * built assets, framework boot with this release's cached config, and the
 * readiness probe from the CLI (DB, Redis, storage with the new code).
 */
task('deploy:smoke', function (): void {
    run('test -f {{release_path}}/public/build/manifest.json');
    run('cd {{release_path}} && php artisan about --only=environment --no-interaction');
    run('cd {{release_path}} && php artisan ops:readiness --no-interaction');
});

/*
 * Remember the release that served traffic before the switch; it is the
 * only automatic rollback target (ADR-022). Persisted in .dep so an
 * operator can see it after an interrupted deploy. The first deploy has no
 * target and therefore no automatic rollback.
 */
task('deploy:remember_healthy', function (): void {
    if (test('[ -L {{current_path}} ]')) {
        $healthy = run('readlink -f {{current_path}}');
        set('healthy_release', $healthy);
        run('mkdir -p {{deploy_path}}/.dep && printf "%s\n" '.escapeshellarg($healthy).' > {{deploy_path}}/.dep/healthy_release');
    } else {
        set('healthy_release', '');
    }
});

/*
 * Restart long-running processes on the new code: Horizon finishes its
 * current jobs and systemd starts it again from `current`; the SSR server
 * is stopped and restarted by systemd with the new bundle; running
 * schedule:run invocations are interrupted (the timer starts the next one
 * from the new release).
 */
task('deploy:restart_workers', function (): void {
    run('cd {{current_path}} && php artisan horizon:terminate --no-interaction');
    run('cd {{current_path}} && php artisan schedule:interrupt --no-interaction');
    run('cd {{current_path}} && (php artisan inertia:stop-ssr --no-interaction || true)');
});

function readinessPasses(): bool
{
    $attempts = (int) get('smoke_attempts');

    for ($attempt = 1; $attempt <= $attempts; $attempt++) {
        if (test(get('smoke_command'))) {
            return true;
        }

        writeln(sprintf('<comment>Readiness: próba %d/%d nieudana.</comment>', $attempt, $attempts));

        if ($attempt < $attempts) {
            run('sleep '.(int) get('smoke_interval_seconds'));
        }
    }

    return false;
}

/*
 * Post-switch observation (ADR-022). On failure: exactly one automatic
 * switch back to the remembered healthy release, worker restart, re-check
 * and a critical alert; the deploy then fails. The schema is never rolled
 * back and there is no loop. Without a healthy predecessor (first deploy)
 * only the alert. When the previous release is unhealthy too, further
 * deploys are blocked until the operator removes .dep/deploy_blocked.
 */
task('deploy:smoke:live', function (): void {
    if (readinessPasses()) {
        return;
    }

    $failedRelease = run('readlink -f {{current_path}}');
    $healthy = (string) get('healthy_release');
    run('logger -p user.crit -t starter-deploy '.escapeshellarg('ops.deploy.readiness_failed release='.basename($failedRelease)).' || true');

    if ($healthy === '' || $healthy === $failedRelease || ! test('[ -d '.escapeshellarg($healthy).' ]')) {
        throw new \RuntimeException('Readiness po przełączeniu nie przeszła, brak poprzedniego zdrowego release’u — wymagana decyzja operatora.');
    }

    writeln('<error>Readiness nie przeszła — automatyczny rollback do '.basename($healthy).'.</error>');
    run('cd {{deploy_path}} && ln -sfn '.escapeshellarg($healthy).' current.rollback && mv -T current.rollback current');
    run('printf "%s\n" "$(date -u +%Y-%m-%dT%H:%M:%SZ) readiness failed" > '.escapeshellarg($failedRelease.'/BAD_RELEASE'));
    invoke('deploy:restart_workers');

    $recovered = readinessPasses();

    if (! $recovered) {
        run('printf "%s\n" "$(date -u +%Y-%m-%dT%H:%M:%SZ) rollback target unhealthy" > {{deploy_path}}/.dep/deploy_blocked');
    }

    run('logger -p user.crit -t starter-deploy '.escapeshellarg(sprintf(
        'ops.deploy.rolled_back from=%s to=%s recovered=%s',
        basename($failedRelease),
        basename($healthy),
        $recovered ? 'yes' : 'no',
    )).' || true');

    throw new \RuntimeException($recovered
        ? 'Wydanie cofnięte automatycznie do '.basename($healthy).'; poprzednia wersja przechodzi readiness.'
        : 'Wydanie cofnięte, ale poprzednia wersja również nie przechodzi readiness — alarm operatora, kolejne deploye wstrzymane.');
});

/*
 * Explicit flow instead of the recipe default: the default runs
 * artisan:migrate unconditionally and artisan:optimize, bypassing the
 * DEPLOY_ALLOW_MIGRATIONS gate above.
 */
task('deploy', [
    'deploy:check_blocked',
    'deploy:prepare',
    'deploy:vendors',
    'artisan:storage:link',
    'artisan:config:cache',
    'artisan:route:cache',
    'artisan:view:cache',
    'artisan:event:cache',
    'deploy:migrate',
    'deploy:smoke',
    'deploy:remember_healthy',
    'deploy:symlink',
    'deploy:restart_workers',
    'deploy:smoke:live',
    'deploy:unlock',
    'deploy:cleanup',
    'deploy:success',
]);

after('deploy:failed', 'deploy:unlock');
