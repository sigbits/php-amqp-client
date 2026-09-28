<?php

declare(strict_types=1);

namespace Sigbits\Amqp\Transport;

use Closure;

final readonly class StreamConnector
{
    /**
     * @param null|Closure(string, float, array<string, mixed>): mixed $streamFactory
     */
    public function __construct(
        private ?Closure $streamFactory = null,
    ) {
    }

    /**
     * @return resource
     */
    public function connect(ConnectionUri $uri, float $timeoutSeconds = 30.0): mixed
    {
        $target = sprintf(
            '%s://%s:%d',
            $uri->usesTls() ? 'tls' : 'tcp',
            $uri->host,
            $uri->port,
        );
        $stream = $this->openStream($target, $timeoutSeconds, $uri->streamContextOptions());

        if (!is_resource($stream)) {
            throw TransportException::connectionFailed($target, 'stream factory did not return a resource');
        }

        return $stream;
    }

    /**
     * @param array<string, mixed> $contextOptions
     */
    private function openStream(string $target, float $timeoutSeconds, array $contextOptions): mixed
    {
        if ($this->streamFactory !== null) {
            return ($this->streamFactory)($target, $timeoutSeconds, $contextOptions);
        }

        $context = stream_context_create($contextOptions);
        $errno = 0;
        $errstr = '';
        $stream = @stream_socket_client(
            address: $target,
            error_code: $errno,
            error_message: $errstr,
            timeout: $timeoutSeconds,
            flags: STREAM_CLIENT_CONNECT,
            context: $context,
        );

        if ($stream === false) {
            throw TransportException::connectionFailed($target, $errstr !== '' ? $errstr : sprintf('error %d', $errno));
        }

        return $stream;
    }
}
