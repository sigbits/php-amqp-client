<?php

declare(strict_types=1);

namespace Sigbits\Amqp\Client;

use Sigbits\Amqp\Client\Internal\ClientObjectFactory;
use Sigbits\Amqp\Engine\ConnectionEngine;
use Sigbits\Amqp\Engine\ConnectionState;
use Sigbits\Amqp\Engine\SessionEngine;
use Sigbits\Amqp\Engine\SessionState;
use Sigbits\Amqp\Protocol\Sasl\SaslClient;
use Sigbits\Amqp\Transport\ConnectionUri;
use Sigbits\Amqp\Transport\SaslStreamConnector;
use Sigbits\Amqp\Transport\TlsOptions;
use Sigbits\Amqp\Transport\TransportException;

final class Connection
{
    private bool $closed = false;

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
    ): self {
        return self::connectUsingConnector(
            uri: $uri,
            connector: new SaslStreamConnector(),
            saslClient: $saslClient,
            containerId: $containerId,
            timeoutSeconds: $timeoutSeconds,
            tls: $tls,
        );
    }

    private static function connectUsingConnector(
        string $uri,
        SaslStreamConnector $connector,
        ?SaslClient $saslClient = null,
        string $containerId = 'sigbits-php-amqp-client',
        float $timeoutSeconds = 30.0,
        ?TlsOptions $tls = null,
    ): self {
        $connectionUri = ConnectionUri::parse($uri, $tls);
        $stream = $connector->connect(
            $connectionUri,
            $saslClient ?? self::saslClientFromUri($connectionUri),
            $timeoutSeconds,
        );
        self::setReadTimeout($stream, $timeoutSeconds);
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

    public function beginSession(int $channel = 0): Session
    {
        $engine = new SessionEngine(localChannel: $channel);

        $this->writeAll($engine->begin());

        while ($engine->state() !== SessionState::Mapped) {
            $engine->push($this->readFrame());
        }

        return ClientObjectFactory::session($engine, $channel, $this->writeAll(...), $this->readFrame(...));
    }

    public function close(): void
    {
        if ($this->closed) {
            return;
        }

        if ($this->engine->state() === ConnectionState::Opened) {
            $this->writeAll($this->engine->close());
        }

        if (is_resource($this->stream)) {
            fclose($this->stream);
        }

        $this->closed = true;
    }

    private static function saslClientFromUri(ConnectionUri $uri): SaslClient
    {
        if ($uri->username !== null) {
            return SaslClient::plain($uri->username, $uri->password ?? '');
        }

        return SaslClient::anonymous();
    }

    /**
     * @param resource $stream
     */
    private static function setReadTimeout(mixed $stream, float $timeoutSeconds): void
    {
        $seconds = (int) floor($timeoutSeconds);
        $microseconds = (int) (($timeoutSeconds - $seconds) * 1_000_000);

        stream_set_timeout($stream, $seconds, $microseconds);
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
            $chunkLength = @fwrite($this->stream, substr($bytes, $written));

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

        $frame = $header . $this->readExactly($frameSize - 8);

        if ($this->isConnectionClose($frame)) {
            $this->engine->push($frame);

            throw ClientException::remoteConnectionClosed();
        }

        return $frame;
    }

    private function readExactly(int $length): string
    {
        $bytes = '';

        while (strlen($bytes) < $length) {
            $chunk = fread($this->stream, $length - strlen($bytes));

            if ($chunk === false || $chunk === '') {
                $metadata = stream_get_meta_data($this->stream);

                if ($metadata['timed_out'] === true) {
                    throw TransportException::readTimedOut();
                }

                throw TransportException::unexpectedEndOfStream();
            }

            $bytes .= $chunk;
        }

        return $bytes;
    }

    private function isConnectionClose(string $frame): bool
    {
        return substr($frame, 8, 3) === "\x00\x53\x18";
    }
}
