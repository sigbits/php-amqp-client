<?php

declare(strict_types=1);

namespace Sigbits\Amqp\Client;

use Sigbits\Amqp\Engine\ConnectionEngine;
use Sigbits\Amqp\Engine\ConnectionState;
use Sigbits\Amqp\Protocol\Sasl\SaslClient;
use Sigbits\Amqp\Transport\ConnectionUri;
use Sigbits\Amqp\Transport\SaslStreamConnector;
use Sigbits\Amqp\Transport\TlsOptions;
use Sigbits\Amqp\Transport\TransportException;

final class Connection
{
    private function __construct(
        private mixed $stream,
        private readonly ConnectionEngine $engine,
    ) {
    }

    public static function connect(
        string $uri,
        ?SaslClient $saslClient = null,
        string $containerId = 'sigbits-php-amqp-client',
        float $timeoutSeconds = 30.0,
        ?TlsOptions $tls = null,
        ?SaslStreamConnector $connector = null,
    ): self {
        $connectionUri = ConnectionUri::parse($uri, $tls);
        $stream = ($connector ?? new SaslStreamConnector())->connect(
            $connectionUri,
            $saslClient ?? self::saslClientFromUri($connectionUri),
            $timeoutSeconds,
        );
        $engine = new ConnectionEngine(localContainerId: $containerId);
        $connection = new self($stream, $engine);

        $connection->writeAll($engine->start());
        $engine->push($connection->readExactly(8));

        while ($engine->state() !== ConnectionState::Opened) {
            $engine->push($connection->readFrame());
        }

        return $connection;
    }

    public function state(): ConnectionState
    {
        return $this->engine->state();
    }

    public function close(): void
    {
        $this->writeAll($this->engine->close());

        if (is_resource($this->stream)) {
            fclose($this->stream);
        }
    }

    private static function saslClientFromUri(ConnectionUri $uri): SaslClient
    {
        if ($uri->username !== null) {
            return SaslClient::plain($uri->username, $uri->password ?? '');
        }

        return SaslClient::anonymous();
    }

    /**
     * @param list<string> $frames
     */
    private function writeAll(array $frames): void
    {
        foreach ($frames as $frame) {
            $this->writeFully($frame);
        }
    }

    private function writeFully(string $bytes): void
    {
        $written = 0;

        while ($written < strlen($bytes)) {
            $chunkLength = fwrite($this->stream, substr($bytes, $written));

            if ($chunkLength === false || $chunkLength === 0) {
                throw TransportException::writeFailed();
            }

            $written += $chunkLength;
        }
    }

    private function readFrame(): string
    {
        $header = $this->readExactly(8);
        $frameSize = (ord($header[0]) << 24)
            | (ord($header[1]) << 16)
            | (ord($header[2]) << 8)
            | ord($header[3]);

        return $header . $this->readExactly($frameSize - 8);
    }

    private function readExactly(int $length): string
    {
        $bytes = '';

        while (strlen($bytes) < $length) {
            $chunk = fread($this->stream, $length - strlen($bytes));

            if ($chunk === false || $chunk === '') {
                throw TransportException::unexpectedEndOfStream();
            }

            $bytes .= $chunk;
        }

        return $bytes;
    }
}
