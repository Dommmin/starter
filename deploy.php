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

task('deploy:update_code', function (): void {
    $artifactPath = get('artifact_path');

    if (! is_file($artifactPath)) {
        throw new \RuntimeException(sprintf('Brak artefaktu release: %s.', $artifactPath));
    }

    upload($artifactPath, '{{release_path}}/release.tar.gz');
    run('test "$(sha256sum {{release_path}}/release.tar.gz | cut -d " " -f 1)" = "{{artifact_sha256}}"');
    run('tar -xzf {{release_path}}/release.tar.gz -C {{release_path}} --no-same-owner');
    run('rm {{release_path}}/release.tar.gz');
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

task('deploy:smoke', function (): void {
    run('test -f {{release_path}}/public/build/manifest.json');
    run('cd {{release_path}} && php artisan config:cache --no-interaction');
    run('cd {{release_path}} && php artisan about --only=environment --no-interaction');
});

task('deploy:smoke:live', function (): void {
    run('curl --fail --silent --show-error --max-time 5 http://127.0.0.1/up > /dev/null');
});

before('deploy:symlink', 'deploy:smoke');
after('deploy:symlink', 'deploy:smoke:live');
after('deploy:failed', 'deploy:unlock');
