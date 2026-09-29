<?php

declare(strict_types=1);

namespace Sigbits\Amqp\Tests\Client;

use PHPUnit\Framework\TestCase;
use Sigbits\Amqp\Client\Connection;
use Sigbits\Amqp\Engine\ConnectionState;
use Sigbits\Amqp\Engine\SessionState;
use Sigbits\Amqp\Transport\SaslStreamAuthenticator;
use Sigbits\Amqp\Transport\SaslStreamConnector;
use Sigbits\Amqp\Transport\StreamConnector;

final class ConnectionTest extends TestCase
{
    public function testConnectOpensAuthenticatedAmqpConnection(): void
    {
        [$client, $server] = $this->streamPair();
        fwrite($server, $this->serverGreeting());
        $connector = new SaslStreamConnector(
            streamConnector: new StreamConnector(static fn (): mixed => $client),
            authenticator: new SaslStreamAuthenticator(),
        );

        $connection = Connection::connect(
            'amqp://guest:secret@broker.example.test',
            containerId: 'client',
            timeoutSeconds: 1.0,
            connector: $connector,
        );

        self::assertSame(ConnectionState::Opened, $connection->state());
        self::assertStringContainsString('PLAIN', $this->readAvailable($server));
    }

    public function testCloseEmitsAmqpCloseFrameAndClosesStream(): void
    {
        [$client, $server] = $this->streamPair();
        fwrite($server, $this->serverGreeting());
        $connector = new SaslStreamConnector(
            streamConnector: new StreamConnector(static fn (): mixed => $client),
            authenticator: new SaslStreamAuthenticator(),
        );
        $connection = Connection::connect(
            'amqp://guest:secret@broker.example.test',
            containerId: 'client',
            timeoutSeconds: 1.0,
            connector: $connector,
        );
        $this->readAvailable($server);

        $connection->close();

        self::assertSame(ConnectionState::CloseSent, $connection->state());
        self::assertSame("\x00\x00\x00\x0c\x02\x00\x00\x00\x00\x53\x18\x45", $this->readAvailable($server));
    }

    public function testBeginSessionMapsPublicSession(): void
    {
        [$client, $server] = $this->streamPair();
        fwrite($server, $this->serverGreeting() . $this->beginFrame(channel: 1));
        $connector = new SaslStreamConnector(
            streamConnector: new StreamConnector(static fn (): mixed => $client),
            authenticator: new SaslStreamAuthenticator(),
        );
        $connection = Connection::connect(
            'amqp://guest:secret@broker.example.test',
            containerId: 'client',
            timeoutSeconds: 1.0,
            connector: $connector,
        );
        $this->readAvailable($server);

        $session = $connection->beginSession(channel: 1);

        self::assertSame(SessionState::Mapped, $session->state());
        self::assertSame($this->beginFrame(channel: 1), $this->readAvailable($server));
    }

    public function testEndSessionEmitsEndFrameAndWaitsForRemoteEnd(): void
    {
        [$client, $server] = $this->streamPair();
        fwrite($server, $this->serverGreeting() . $this->beginFrame(channel: 1) . $this->endFrame(channel: 1));
        $connector = new SaslStreamConnector(
            streamConnector: new StreamConnector(static fn (): mixed => $client),
            authenticator: new SaslStreamAuthenticator(),
        );
        $connection = Connection::connect(
            'amqp://guest:secret@broker.example.test',
            containerId: 'client',
            timeoutSeconds: 1.0,
            connector: $connector,
        );
        $session = $connection->beginSession(channel: 1);
        $this->readAvailable($server);

        $session->end();

        self::assertSame(SessionState::Ended, $session->state());
        self::assertSame($this->endFrame(channel: 1), $this->readAvailable($server));
    }

    /**
     * @return array{resource, resource}
     */
    private function streamPair(): array
    {
        $pair = stream_socket_pair(STREAM_PF_UNIX, STREAM_SOCK_STREAM, STREAM_IPPROTO_IP);

        if ($pair === false) {
            self::fail('Could not create stream pair for connection test.');
        }

        return $pair;
    }

    private function serverGreeting(): string
    {
        return "AMQP\x03\x01\x00\x00"
            . $this->frame(
                "\x00\x53\x40\xc0\x15\x01\xe0\x12\x02\xa3\x09ANONYMOUS\x05PLAIN",
                type: 1,
            )
            . $this->frame("\x00\x53\x44\xc0\x03\x01\x50\x00", type: 1)
            . "AMQP\x00\x01\x00\x00"
            . $this->frame("\x00\x53\x10\xc0\x09\x01\xa1\x06server");
    }

    private function beginFrame(int $channel): string
    {
        return $this->frame(
            "\x00\x53\x11\xc0\x11\x04\x40"
            . "\x70\x00\x00\x00\x00"
            . "\x70\x7f\xff\xff\xff"
            . "\x70\x7f\xff\xff\xff",
            channel: $channel,
        );
    }

    private function endFrame(int $channel): string
    {
        return $this->frame("\x00\x53\x17\x45", channel: $channel);
    }

    private function frame(string $payload, int $type = 0, int $channel = 0): string
    {
        return pack('NCCn', 8 + strlen($payload), 2, $type, $channel) . $payload;
    }

    /**
     * @param resource $stream
     */
    private function readAvailable(mixed $stream): string
    {
        stream_set_blocking($stream, false);
        $bytes = stream_get_contents($stream);
        stream_set_blocking($stream, true);

        if ($bytes === false) {
            self::fail('Could not read available stream bytes.');
        }

        return $bytes;
    }
}
