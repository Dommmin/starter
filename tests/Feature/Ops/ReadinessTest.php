<?php

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Redis;
use Laravel\Horizon\Contracts\MasterSupervisorRepository;

beforeEach(function () {
    config([
        'ops.health.token' => 'synthetic-health-token',
        'ops.health.redis' => true,
        'ops.health.horizon' => true,
        'ops.health.scheduler' => true,
        'ops.health.scanner' => false,
        'queue.default' => 'redis',
    ]);

    Redis::shouldReceive('connection')->with('default')->andReturnSelf()->byDefault();
    Redis::shouldReceive('ping')->andReturn(true)->byDefault();

    $this->mock(MasterSupervisorRepository::class)
        ->shouldReceive('all')
        ->andReturn([(object) ['name' => 'master', 'status' => 'running']])
        ->byDefault();

    Cache::forever('ops:heartbeat:scheduler', now()->getTimestamp());
});

function readinessDetails(): array
{
    return ['X-Health-Token' => 'synthetic-health-token'];
}

test('readiness reports ok without session cookies or service details', function () {
    $response = $this->get('/health/ready');

    $response->assertOk()
        ->assertExactJson(['status' => 'ok'])
        ->assertHeader('X-Robots-Tag', 'noindex, nofollow')
        ->assertHeaderMissing('Set-Cookie');

    expect($response->headers->get('Cache-Control'))->toContain('no-store');
});

test('a valid health token reveals the per-check results', function () {
    $this->get('/health/ready', readinessDetails())
        ->assertOk()
        ->assertExactJson([
            'status' => 'ok',
            'checks' => [
                'database' => 'ok',
                'redis' => 'ok',
                'storage' => 'ok',
                'queue' => 'ok',
                'scheduler' => 'ok',
                'scanner' => 'skipped',
            ],
        ]);
});

test('a wrong health token does not reveal details', function () {
    $this->get('/health/ready', ['X-Health-Token' => 'wrong'])
        ->assertOk()
        ->assertExactJson(['status' => 'ok']);
});

test('an unreachable database makes the node unready', function () {
    config([
        'database.connections.unreachable' => [
            'driver' => 'pgsql',
            'host' => '127.0.0.1',
            'port' => 1,
            'database' => 'none',
            'username' => 'none',
            'password' => '',
            'options' => [PDO::ATTR_TIMEOUT => 1],
        ],
        'ops.health.database_connection' => 'unreachable',
    ]);

    $this->get('/health/ready', readinessDetails())
        ->assertServiceUnavailable()
        ->assertJsonPath('status', 'fail')
        ->assertJsonPath('checks.database', 'fail')
        ->assertJsonPath('checks.storage', 'ok');
});

test('an unreachable Redis makes the node unready without leaking the exception', function () {
    Redis::shouldReceive('ping')->andThrow(new RedisException('Connection refused to secret-host:6379'));

    $response = $this->get('/health/ready', readinessDetails());

    $response->assertServiceUnavailable()
        ->assertJsonPath('status', 'fail')
        ->assertJsonPath('checks.redis', 'fail');

    expect($response->getContent())->not->toContain('secret-host');
});

test('a stopped Horizon or a stale scheduler heartbeat only degrades readiness', function () {
    $this->mock(MasterSupervisorRepository::class)->shouldReceive('all')->andReturn([]);
    Cache::forever('ops:heartbeat:scheduler', now()->subMinutes(10)->getTimestamp());

    $this->get('/health/ready', readinessDetails())
        ->assertOk()
        ->assertJsonPath('status', 'degraded')
        ->assertJsonPath('checks.queue', 'fail')
        ->assertJsonPath('checks.scheduler', 'fail');
});

test('a paused Horizon is reported as a failed queue check', function () {
    $this->mock(MasterSupervisorRepository::class)
        ->shouldReceive('all')
        ->andReturn([(object) ['name' => 'master', 'status' => 'paused']]);

    $this->get('/health/ready', readinessDetails())
        ->assertJsonPath('checks.queue', 'fail');
});

test('disabled checks are skipped', function () {
    config(['ops.health.redis' => false, 'ops.health.scheduler' => false, 'queue.default' => 'sync']);

    $this->get('/health/ready', readinessDetails())
        ->assertOk()
        ->assertJsonPath('checks.redis', 'skipped')
        ->assertJsonPath('checks.queue', 'skipped')
        ->assertJsonPath('checks.scheduler', 'skipped');
});

test('readiness is rate limited per client', function () {
    config(['ops.health.rate_limit_per_minute' => 2]);

    $this->get('/health/ready')->assertOk();
    $this->get('/health/ready')->assertOk();
    $this->get('/health/ready')->assertTooManyRequests();
});

test('the readiness command fails only when a critical dependency is down', function () {
    $this->artisan('ops:readiness')->assertSuccessful();

    Cache::forever('ops:heartbeat:scheduler', now()->subHour()->getTimestamp());
    $this->artisan('ops:readiness')->assertSuccessful();
    $this->artisan('ops:readiness --strict')->assertFailed();

    Redis::shouldReceive('ping')->andThrow(new RedisException('down'));
    $this->artisan('ops:readiness')->assertFailed();
});
