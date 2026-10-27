<?php

use App\Enums\HealthCheckStatus;
use App\Enums\HealthStatus;
use App\Services\Health\ReadinessProbe;
use App\Services\Health\ReadinessReport;
use App\Services\Media\ImageFormatSupport;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::fake('media');
    Storage::fake('public');

    // A public directory with the built assets, independent of the host build.
    $public = storage_path('framework/testing/doctor-public');
    File::ensureDirectoryExists("{$public}/build");
    File::ensureDirectoryExists("{$public}/storage");
    File::put("{$public}/build/manifest.json", '{}');
    app()->usePublicPath($public);

    config(['inertia.ssr.enabled' => false, 'app.debug' => false, 'mail.default' => 'smtp']);

    $this->readiness = fn (array $checks) => $this->mock(ReadinessProbe::class)
        ->shouldReceive('run')
        ->andReturn(new ReadinessReport(HealthStatus::Ok, $checks));
    ($this->readiness)(['database' => HealthCheckStatus::Ok, 'queue' => HealthCheckStatus::Ok]);
});

afterEach(function () {
    File::deleteDirectory(storage_path('framework/testing/doctor-public'));
});

function formatSupport(array $missing): void
{
    app()->instance(ImageFormatSupport::class, new class($missing) extends ImageFormatSupport
    {
        public function __construct(private readonly array $missing) {}

        public function encodes(string $format): bool
        {
            return ! in_array($format, $this->missing, true);
        }
    });
}

test('a healthy host passes', function () {
    formatSupport([]);

    $this->artisan('ops:doctor', ['--strict' => true])->assertSuccessful();
});

test('a missing avif codec is a warning that fails only in strict mode', function () {
    formatSupport(['avif']);

    $this->artisan('ops:doctor')
        ->expectsOutputToContain('variants skip it')
        ->assertSuccessful();

    $this->artisan('ops:doctor', ['--strict' => true])->assertFailed();
});

test('blocking problems fail the check', function (Closure $breakHost) {
    formatSupport([]);
    $breakHost->call($this);

    $this->artisan('ops:doctor')->assertFailed();
})->with([
    'no jpeg codec' => [fn () => formatSupport(['jpeg'])],
    'debug in production' => [function () {
        app()->detectEnvironment(fn (): string => 'production');
        config(['app.debug' => true]);
    }],
    'missing vite manifest' => [fn () => File::delete(public_path('build/manifest.json'))],
    'critical readiness check failed' => [fn () => ($this->readiness)(['database' => HealthCheckStatus::Fail])],
]);
