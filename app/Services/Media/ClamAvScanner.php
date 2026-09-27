<?php

namespace App\Services\Media;

use App\Services\Media\Exceptions\MalwareScannerUnavailable;
use Illuminate\Container\Attributes\Config;

/**
 * Minimal clamd client: `zINSTREAM` over TCP with length-prefixed chunks.
 * Any connection, timeout or protocol problem is reported as unavailable,
 * never as clean.
 *
 * @see https://docs.clamav.net/manual/Usage/Scanning.html#clamd
 */
final class ClamAvScanner implements MalwareScanner
{
    public function __construct(
        #[Config('media.scanner.host')] private readonly string $host,
        #[Config('media.scanner.port')] private readonly int $port,
        #[Config('media.scanner.timeout')] private readonly int $timeout,
        #[Config('media.scanner.chunk_bytes')] private readonly int $chunkBytes,
    ) {}

    public function scan($stream): ScanResult
    {
        set_error_handler(static fn (): bool => true);

        try {
            $socket = fsockopen($this->host, $this->port, $errorCode, $errorMessage, $this->timeout);
        } finally {
            restore_error_handler();
        }

        if ($socket === false) {
            throw new MalwareScannerUnavailable("clamd connection failed ({$errorCode}).");
        }

        try {
            stream_set_timeout($socket, $this->timeout);
            $this->write($socket, "zINSTREAM\0");

            while (! feof($stream)) {
                $chunk = fread($stream, max(1, $this->chunkBytes));

                if ($chunk === false) {
                    throw new MalwareScannerUnavailable('Could not read the file to scan.');
                }

                if ($chunk === '') {
                    continue;
                }

                $this->write($socket, pack('N', strlen($chunk)).$chunk);
            }

            $this->write($socket, pack('N', 0));

            return self::parseResponse($this->readResponse($socket));
        } finally {
            fclose($socket);
        }
    }

    /**
     * Interpret a clamd INSTREAM reply such as `stream: OK` or
     * `stream: Eicar-Signature FOUND`.
     *
     * @throws MalwareScannerUnavailable For errors and unknown replies.
     */
    public static function parseResponse(string $response): ScanResult
    {
        $response = trim($response, "\0\r\n ");

        if ($response === 'stream: OK') {
            return ScanResult::clean();
        }

        if (preg_match('/^stream: (.+) FOUND$/', $response, $matches) === 1) {
            return ScanResult::infected(mb_substr($matches[1], 0, 200));
        }

        throw new MalwareScannerUnavailable('Unexpected clamd reply: '.mb_substr($response, 0, 200));
    }

    /**
     * @param  resource  $socket
     */
    private function write($socket, string $payload): void
    {
        $length = strlen($payload);
        $written = 0;

        while ($written < $length) {
            $result = @fwrite($socket, substr($payload, $written));

            if ($result === false || $result === 0) {
                $this->assertNotTimedOut($socket);

                throw new MalwareScannerUnavailable('clamd closed the connection while streaming.');
            }

            $written += $result;
        }
    }

    /**
     * @param  resource  $socket
     */
    private function readResponse($socket): string
    {
        $response = '';

        while (! feof($socket)) {
            $part = fread($socket, 1024);

            if ($part === false) {
                break;
            }

            $this->assertNotTimedOut($socket);
            $response .= $part;

            if (str_contains($response, "\0")) {
                break;
            }
        }

        return $response;
    }

    /**
     * @param  resource  $socket
     */
    private function assertNotTimedOut($socket): void
    {
        $meta = stream_get_meta_data($socket);

        if ($meta['timed_out']) {
            throw new MalwareScannerUnavailable('clamd timed out.');
        }
    }
}
