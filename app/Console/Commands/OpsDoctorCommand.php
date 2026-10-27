<?php

namespace App\Console\Commands;

use App\Enums\HealthCheckStatus;
use App\Services\Health\ReadinessProbe;
use App\Services\Media\ImageFormatSupport;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

/**
 * Host check after provisioning or a deploy: what the readiness probe does
 * not cover (configuration, image codecs, write access, SSR bundle, mail),
 * plus the readiness checks. Run it as the PHP-FPM user (`sudo -u
 * www-data`), because write access depends on who asks. Fails on any
 * `fail`; with --strict also on `warn`.
 */
#[Signature('ops:doctor {--strict : Fail also on warnings}')]
#[Description('Check that this host can run the application')]
class OpsDoctorCommand extends Command
{
    private const string OK = 'ok';

    private const string WARN = 'warn';

    private const string FAIL = 'fail';

    /**
     * Execute the console command.
     */
    public function handle(ReadinessProbe $probe, ImageFormatSupport $formats): int
    {
        $results = [
            ...$this->configuration(),
            ...$this->imageFormats($formats),
            ...$this->writableStorage(),
            ...$this->assets(),
            ...$this->readiness($probe),
        ];

        $this->table(['check', 'status', 'detail'], array_map(
            fn (array $result, string $check): array => [$check, $result[0], $result[1]],
            $results,
            array_keys($results),
        ));

        $statuses = array_column($results, 0);
        $failed = in_array(self::FAIL, $statuses, true)
            || ($this->option('strict') && in_array(self::WARN, $statuses, true));

        return $failed ? self::FAILURE : self::SUCCESS;
    }

    /**
     * @return array<string, array{0: string, 1: string}>
     */
    private function configuration(): array
    {
        $production = app()->isProduction();
        $mailer = (string) config('mail.default');

        return [
            'app key' => config('app.key') ? [self::OK, ''] : [self::FAIL, 'APP_KEY is empty'],
            'debug' => $production && config('app.debug') ? [self::FAIL, 'APP_DEBUG is on in production'] : [self::OK, ''],
            'mail' => $production && in_array($mailer, ['log', 'array'], true)
                ? [self::WARN, "MAIL_MAILER={$mailer}: invitations, password resets and alerts are not delivered"]
                : [self::OK, $mailer],
        ];
    }

    /**
     * AVIF and WebP are optional (variants fall back), JPEG and PNG are not.
     *
     * @return array<string, array{0: string, 1: string}>
     */
    private function imageFormats(ImageFormatSupport $formats): array
    {
        $results = [];

        foreach (['jpeg' => self::FAIL, 'png' => self::FAIL, 'webp' => self::WARN, 'avif' => self::WARN] as $format => $missing) {
            $results["image {$format}"] = $formats->encodes($format)
                ? [self::OK, '']
                : [$missing, 'GD cannot write this format'.($missing === self::WARN ? '; variants skip it' : '')];
        }

        return $results;
    }

    /**
     * @return array<string, array{0: string, 1: string}>
     */
    private function writableStorage(): array
    {
        $directories = [
            'storage framework' => storage_path('framework'),
            'storage logs' => storage_path('logs'),
            'media disk' => Storage::disk((string) config('media.disk'))->path(''),
            'public disk' => Storage::disk((string) config('media.public_disk'))->path(''),
        ];

        $results = [];

        foreach ($directories as $check => $directory) {
            $results[$check] = is_dir($directory) && is_writable($directory)
                ? [self::OK, '']
                : [self::FAIL, "{$directory} is not writable by ".(get_current_user() ?: 'this user')];
        }

        return $results;
    }

    /**
     * @return array<string, array{0: string, 1: string}>
     */
    private function assets(): array
    {
        $results = [
            'vite manifest' => is_file(public_path('build/manifest.json')) ? [self::OK, ''] : [self::FAIL, 'public/build/manifest.json is missing'],
            'storage link' => is_link(public_path('storage')) || is_dir(public_path('storage'))
                ? [self::OK, '']
                : [self::FAIL, 'public/storage is missing; run php artisan storage:link'],
        ];

        if (config('inertia.ssr.enabled')) {
            $results['ssr bundle'] = is_file(base_path('bootstrap/ssr/app.js'))
                ? [self::OK, '']
                : [self::FAIL, 'bootstrap/ssr/app.js is missing; build with npm run build:ssr'];
        }

        return $results;
    }

    /**
     * @return array<string, array{0: string, 1: string}>
     */
    private function readiness(ReadinessProbe $probe): array
    {
        $results = [];

        foreach ($probe->run()->checks as $check => $status) {
            $results[$check] = match ($status) {
                HealthCheckStatus::Fail => [in_array($check, ReadinessProbe::CRITICAL_CHECKS, true) ? self::FAIL : self::WARN, 'readiness check failed'],
                HealthCheckStatus::Skipped => [self::OK, 'skipped'],
                default => [self::OK, ''],
            };
        }

        return $results;
    }
}
