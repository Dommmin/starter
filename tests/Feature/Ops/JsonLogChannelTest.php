<?php

use Illuminate\Support\Facades\Log;

test('the json channel writes one JSON object per line with runtime context', function () {
    $path = storage_path('framework/testing/json-log-'.uniqid().'.log');
    config([
        'logging.channels.json.path' => $path,
        'ops.release' => '20260927.1',
    ]);
    $_SERVER['REQUEST_ID'] = str_repeat('a1', 16);
    putenv('LOG_SERVICE=horizon');

    try {
        Log::channel('json')->critical('ops.test_event', ['job' => 'Example']);

        $record = json_decode(trim((string) file_get_contents($path)), true, flags: JSON_THROW_ON_ERROR);

        expect($record)
            ->message->toBe('ops.test_event')
            ->level_name->toBe('CRITICAL')
            ->context->toBe(['job' => 'Example'])
            ->and($record['extra'])->toMatchArray([
                'service' => 'horizon',
                'environment' => 'testing',
                'release' => '20260927.1',
                'request_id' => str_repeat('a1', 16),
            ]);
    } finally {
        unset($_SERVER['REQUEST_ID']);
        putenv('LOG_SERVICE');
        @unlink($path);
    }
});

test('a malformed request id is not written to the log', function () {
    $path = storage_path('framework/testing/json-log-'.uniqid().'.log');
    config(['logging.channels.json.path' => $path]);
    $_SERVER['REQUEST_ID'] = "forged\ninjected";

    try {
        Log::channel('json')->info('ops.test_event');

        $record = json_decode(trim((string) file_get_contents($path)), true, flags: JSON_THROW_ON_ERROR);

        expect($record['extra'])->not->toHaveKey('request_id');
    } finally {
        unset($_SERVER['REQUEST_ID']);
        @unlink($path);
    }
});
