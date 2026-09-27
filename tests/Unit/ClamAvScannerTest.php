<?php

use App\Services\Media\ClamAvScanner;
use App\Services\Media\Exceptions\MalwareScannerUnavailable;

test('clamd replies are parsed into verdicts', function () {
    expect(ClamAvScanner::parseResponse("stream: OK\0")->clean)->toBeTrue();

    $infected = ClamAvScanner::parseResponse("stream: Win.Test.EICAR_HDB-1 FOUND\0");
    expect($infected->clean)->toBeFalse()
        ->and($infected->signature)->toBe('Win.Test.EICAR_HDB-1');
});

test('errors and unknown replies never count as clean', function (string $reply) {
    expect(fn () => ClamAvScanner::parseResponse($reply))->toThrow(MalwareScannerUnavailable::class);
})->with([
    'size limit' => ["INSTREAM size limit exceeded. ERROR\0"],
    'empty' => [''],
    'garbage' => ['PONG'],
]);

test('an unreachable clamd is reported as unavailable', function () {
    $scanner = new ClamAvScanner('127.0.0.1', 1, 1, 8192);
    $stream = fopen('php://memory', 'r+');
    fwrite($stream, 'data');
    rewind($stream);

    expect(fn () => $scanner->scan($stream))->toThrow(MalwareScannerUnavailable::class);
});
