<?php

declare(strict_types=1);

namespace Sigbits\Amqp\Tests\Client;

use PHPUnit\Framework\TestCase;
use Sigbits\Amqp\Client\ClientException;
use Sigbits\Amqp\Client\Connection;
use Sigbits\Amqp\Engine\ConnectionState;
use Sigbits\Amqp\Engine\ReceiverLinkState;
use Sigbits\Amqp\Engine\SenderLinkState;
use Sigbits\Amqp\Engine\SessionState;
use Sigbits\Amqp\Protocol\Message\Message;
use Sigbits\Amqp\Transport\SaslStreamAuthenticator;
use Sigbits\Amqp\Transport\SaslStreamConnector;
use Sigbits\Amqp\Transport\StreamConnector;
use Sigbits\Amqp\Transport\TransportException;

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

    public function testConnectReportsReadTimeout(): void
    {
        [$client, $server] = $this->streamPair();
        stream_set_timeout($client, 0, 1_000);
        fwrite($server, $this->serverGreetingWithoutOpen());
        $connector = new SaslStreamConnector(
            streamConnector: new StreamConnector(static fn (): mixed => $client),
            authenticator: new SaslStreamAuthenticator(),
        );

        $this->expectException(TransportException::class);
        $this->expectExceptionMessage('Timed out waiting for AMQP stream bytes.');

        Connection::connect(
            'amqp://guest:secret@broker.example.test',
            containerId: 'client',
            timeoutSeconds: 0.001,
            connector: $connector,
        );
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

    public function testCloseIsIdempotent(): void
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
        $this->readAvailable($server);
        $connection->close();

        self::assertSame(ConnectionState::CloseSent, $connection->state());
        self::assertSame('', $this->readAvailable($server));
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

    public function testEndSessionIsIdempotent(): void
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
        $this->readAvailable($server);
        $session->end();

        self::assertSame(SessionState::Ended, $session->state());
        self::assertSame('', $this->readAvailable($server));
    }

    public function testOpenSenderAttachesPublicSender(): void
    {
        [$client, $server] = $this->streamPair();
        fwrite(
            $server,
            $this->serverGreeting()
            . $this->beginFrame(channel: 1)
            . $this->remoteSenderAttachFrame(channel: 1)
            . $this->senderCreditFrame(channel: 1, linkCredit: 2),
        );
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

        $sender = $session->openSender('orders.test', name: 'sender', handle: 0);

        self::assertSame(SenderLinkState::Attached, $sender->state());
        self::assertSame(2, $sender->availableCredit());
        self::assertSame($this->senderAttachFrame(channel: 1), $this->readAvailable($server));
    }

    public function testOpenSenderFailsClearlyWhenRemoteDetachesBeforeCredit(): void
    {
        [$client, $server] = $this->streamPair();
        fwrite(
            $server,
            $this->serverGreeting()
            . $this->beginFrame(channel: 1)
            . $this->remoteSenderAttachFrame(channel: 1)
            . $this->detachFrame(channel: 1, handle: 0),
        );
        $connector = new SaslStreamConnector(
            streamConnector: new StreamConnector(static fn (): mixed => $client),
            authenticator: new SaslStreamAuthenticator(),
        );
        $connection = Connection::connect(
            'amqp://guest:secret@broker.example.test',
            containerId: 'client',
            timeoutSeconds: 0.001,
            connector: $connector,
        );
        $session = $connection->beginSession(channel: 1);

        $this->expectException(ClientException::class);
        $this->expectExceptionMessage('Cannot open AMQP sender link because the remote peer detached it.');

        $session->openSender('orders.test', name: 'sender', handle: 0);
    }

    public function testOpenSenderReportsRemoteDetachErrorDetails(): void
    {
        [$client, $server] = $this->streamPair();
        fwrite(
            $server,
            $this->serverGreeting()
            . $this->beginFrame(channel: 1)
            . $this->remoteSenderAttachFrame(channel: 1)
            . $this->detachFrameWithError(channel: 1, handle: 0),
        );
        $connector = new SaslStreamConnector(
            streamConnector: new StreamConnector(static fn (): mixed => $client),
            authenticator: new SaslStreamAuthenticator(),
        );
        $connection = Connection::connect(
            'amqp://guest:secret@broker.example.test',
            containerId: 'client',
            timeoutSeconds: 0.001,
            connector: $connector,
        );
        $session = $connection->beginSession(channel: 1);

        $this->expectException(ClientException::class);
        $this->expectExceptionMessage(
            'Cannot open AMQP sender link because the remote peer detached it: '
            . 'amqp:invalid-field - received Attach with remote null terminus.',
        );

        $session->openSender('orders.test', name: 'sender', handle: 0);
    }

    public function testSenderSendEmitsTransferAfterCreditArrives(): void
    {
        [$client, $server] = $this->streamPair();
        fwrite(
            $server,
            $this->serverGreeting()
            . $this->beginFrame(channel: 1)
            . $this->remoteSenderAttachFrame(channel: 1)
            . $this->senderCreditFrame(channel: 1, linkCredit: 1),
        );
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
        $sender = $session->openSender('orders.test', name: 'sender', handle: 0);
        $this->readAvailable($server);

        $sender->send('hello');

        self::assertSame(0, $sender->availableCredit());
        self::assertSame($this->transferFrame(channel: 1, body: 'hello'), $this->readAvailable($server));
    }

    public function testSenderDetachEmitsDetachFrameAndWaitsForRemoteDetach(): void
    {
        [$client, $server] = $this->streamPair();
        fwrite(
            $server,
            $this->serverGreeting()
            . $this->beginFrame(channel: 1)
            . $this->remoteSenderAttachFrame(channel: 1)
            . $this->senderCreditFrame(channel: 1, linkCredit: 1)
            . $this->detachFrame(channel: 1, handle: 0),
        );
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
        $sender = $session->openSender('orders.test', name: 'sender', handle: 0);
        $this->readAvailable($server);

        $sender->detach();

        self::assertSame(SenderLinkState::Detached, $sender->state());
        self::assertSame($this->detachFrame(channel: 1, handle: 0), $this->readAvailable($server));
    }

    public function testSenderSendAfterDetachFailsClearly(): void
    {
        [$client, $server] = $this->streamPair();
        fwrite(
            $server,
            $this->serverGreeting()
            . $this->beginFrame(channel: 1)
            . $this->remoteSenderAttachFrame(channel: 1)
            . $this->senderCreditFrame(channel: 1, linkCredit: 1)
            . $this->detachFrame(channel: 1, handle: 0),
        );
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
        $sender = $session->openSender('orders.test', name: 'sender', handle: 0);
        $sender->detach();

        $this->expectException(ClientException::class);
        $this->expectExceptionMessage('Cannot send on detached AMQP sender link.');

        $sender->send('hello');
    }

    public function testOpenReceiverAttachesPublicReceiverAndGrantsCredit(): void
    {
        [$client, $server] = $this->streamPair();
        fwrite(
            $server,
            $this->serverGreeting()
            . $this->beginFrame(channel: 1)
            . $this->remoteReceiverAttachFrame(channel: 1),
        );
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

        $receiver = $session->openReceiver('orders.test', name: 'receiver', handle: 1, credit: 2);

        self::assertSame(ReceiverLinkState::Attached, $receiver->state());
        self::assertSame(
            $this->receiverAttachFrame(channel: 1) . $this->receiverCreditFrame(channel: 1, handle: 1, linkCredit: 2),
            $this->readAvailable($server),
        );
    }

    public function testOpenReceiverFailsClearlyWhenRemoteDetachesBeforeAttach(): void
    {
        [$client, $server] = $this->streamPair();
        fwrite(
            $server,
            $this->serverGreeting()
            . $this->beginFrame(channel: 1)
            . $this->detachFrame(channel: 1, handle: 1),
        );
        $connector = new SaslStreamConnector(
            streamConnector: new StreamConnector(static fn (): mixed => $client),
            authenticator: new SaslStreamAuthenticator(),
        );
        $connection = Connection::connect(
            'amqp://guest:secret@broker.example.test',
            containerId: 'client',
            timeoutSeconds: 0.001,
            connector: $connector,
        );
        $session = $connection->beginSession(channel: 1);

        $this->expectException(ClientException::class);
        $this->expectExceptionMessage('Cannot open AMQP receiver link because the remote peer detached it.');

        $session->openReceiver('orders.test', name: 'receiver', handle: 1);
    }

    public function testOpenReceiverReportsRemoteDetachErrorDetails(): void
    {
        [$client, $server] = $this->streamPair();
        fwrite(
            $server,
            $this->serverGreeting()
            . $this->beginFrame(channel: 1)
            . $this->detachFrameWithError(channel: 1, handle: 1),
        );
        $connector = new SaslStreamConnector(
            streamConnector: new StreamConnector(static fn (): mixed => $client),
            authenticator: new SaslStreamAuthenticator(),
        );
        $connection = Connection::connect(
            'amqp://guest:secret@broker.example.test',
            containerId: 'client',
            timeoutSeconds: 0.001,
            connector: $connector,
        );
        $session = $connection->beginSession(channel: 1);

        $this->expectException(ClientException::class);
        $this->expectExceptionMessage(
            'Cannot open AMQP receiver link because the remote peer detached it: '
            . 'amqp:invalid-field - received Attach with remote null terminus.',
        );

        $session->openReceiver('orders.test', name: 'receiver', handle: 1);
    }

    public function testReceiverReceiveReadsTransferMessage(): void
    {
        [$client, $server] = $this->streamPair();
        fwrite(
            $server,
            $this->serverGreeting()
            . $this->beginFrame(channel: 1)
            . $this->remoteReceiverAttachFrame(channel: 1)
            . $this->transferFrame(channel: 1, body: 'hello'),
        );
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
        $receiver = $session->openReceiver('orders.test', name: 'receiver', handle: 1);
        $this->readAvailable($server);

        self::assertEquals(new Message(body: 'hello'), $receiver->receive(timeoutMilliseconds: 100));
    }

    public function testReceiverDetachEmitsDetachFrameAndWaitsForRemoteDetach(): void
    {
        [$client, $server] = $this->streamPair();
        fwrite(
            $server,
            $this->serverGreeting()
            . $this->beginFrame(channel: 1)
            . $this->remoteReceiverAttachFrame(channel: 1)
            . $this->detachFrame(channel: 1, handle: 1),
        );
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
        $receiver = $session->openReceiver('orders.test', name: 'receiver', handle: 1);
        $this->readAvailable($server);

        $receiver->detach();

        self::assertSame(ReceiverLinkState::Detached, $receiver->state());
        self::assertSame($this->detachFrame(channel: 1, handle: 1), $this->readAvailable($server));
    }

    public function testReceiverReceiveAfterDetachFailsClearly(): void
    {
        [$client, $server] = $this->streamPair();
        fwrite(
            $server,
            $this->serverGreeting()
            . $this->beginFrame(channel: 1)
            . $this->remoteReceiverAttachFrame(channel: 1)
            . $this->detachFrame(channel: 1, handle: 1),
        );
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
        $receiver = $session->openReceiver('orders.test', name: 'receiver', handle: 1);
        $receiver->detach();

        $this->expectException(ClientException::class);
        $this->expectExceptionMessage('Cannot receive from detached AMQP receiver link.');

        $receiver->receive(timeoutMilliseconds: 100);
    }

    public function testEndSessionIgnoresLinkFramesBeforeRemoteEnd(): void
    {
        [$client, $server] = $this->streamPair();
        fwrite(
            $server,
            $this->serverGreeting()
            . $this->beginFrame(channel: 1)
            . $this->remoteSenderAttachFrame(channel: 1)
            . $this->senderCreditFrame(channel: 1, linkCredit: 1)
            . $this->senderCreditFrame(channel: 1, linkCredit: 5)
            . $this->endFrame(channel: 1),
        );
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
        $sender = $session->openSender('orders.test', name: 'sender', handle: 0);
        $sender->send('hello');
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
        return $this->serverGreetingWithoutOpen()
            . $this->frame("\x00\x53\x10\xc0\x09\x01\xa1\x06server");
    }

    private function serverGreetingWithoutOpen(): string
    {
        return "AMQP\x03\x01\x00\x00"
            . $this->frame(
                "\x00\x53\x40\xc0\x15\x01\xe0\x12\x02\xa3\x09ANONYMOUS\x05PLAIN",
                type: 1,
            )
            . $this->frame("\x00\x53\x44\xc0\x03\x01\x50\x00", type: 1)
            . "AMQP\x00\x01\x00\x00";
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

    private function detachFrame(int $channel, int $handle): string
    {
        return $this->frame(
            "\x00\x53\x16\xc0\x06\x01"
            . "\x70" . pack('N', $handle),
            channel: $channel,
        );
    }

    private function detachFrameWithError(int $channel, int $handle): string
    {
        return $this->frame(
            "\x00\x53\x16\xc0\x4d\x03"
            . "\x70" . pack('N', $handle)
            . "\x41"
            . "\x00\x53\x1d\xc0\x41\x02"
            . "\xa3\x12amqp:invalid-field"
            . "\xa1\x2areceived Attach with remote null terminus.",
            channel: $channel,
        );
    }

    private function senderAttachFrame(int $channel): string
    {
        return $this->frame(
            "\x00\x53\x12\xc0\x3a\x0a\xa1\x06sender"
            . "\x70\x00\x00\x00\x00"
            . "\x42"
            . "\x40\x40"
            . "\x00\x53\x28\xc0\x0e\x01\xa1\x0borders.test"
            . "\x00\x53\x29\xc0\x0e\x01\xa1\x0borders.test"
            . "\x40\x40\x43",
            channel: $channel,
        );
    }

    private function remoteSenderAttachFrame(int $channel): string
    {
        return $this->frame(
            "\x00\x53\x12\xc0\x0f\x03\xa1\x06sender"
            . "\x70\x00\x00\x00\x00"
            . "\x41",
            channel: $channel,
        );
    }

    private function senderCreditFrame(int $channel, int $linkCredit): string
    {
        return $this->frame(
            "\x00\x53\x13\xc0\x14\x07"
            . "\x40\x40\x40\x40"
            . "\x70\x00\x00\x00\x00"
            . "\x70\x00\x00\x00\x00"
            . "\x70" . pack('N', $linkCredit),
            channel: $channel,
        );
    }

    private function transferFrame(int $channel, string $body): string
    {
        return $this->frame(
            "\x00\x53\x14\xc0\x1e\x06"
            . "\x70\x00\x00\x00\x00"
            . "\x70\x00\x00\x00\x00"
            . "\xa0\x0adelivery-0"
            . "\x70\x00\x00\x00\x00"
            . "\x40"
            . "\x42"
            . "\x00\x53\x75\xa0" . chr(strlen($body)) . $body,
            channel: $channel,
        );
    }

    private function receiverAttachFrame(int $channel): string
    {
        return $this->frame(
            "\x00\x53\x12\xc0\x39\x07\xa1\x08receiver"
            . "\x70\x00\x00\x00\x01"
            . "\x41"
            . "\x40\x40"
            . "\x00\x53\x28\xc0\x0e\x01\xa1\x0borders.test"
            . "\x00\x53\x29\xc0\x0e\x01\xa1\x0borders.test",
            channel: $channel,
        );
    }

    private function remoteReceiverAttachFrame(int $channel): string
    {
        return $this->frame(
            "\x00\x53\x12\xc0\x11\x03\xa1\x08receiver"
            . "\x70\x00\x00\x00\x01"
            . "\x42",
            channel: $channel,
        );
    }

    private function receiverCreditFrame(int $channel, int $handle, int $linkCredit): string
    {
        return $this->frame(
            "\x00\x53\x13\xc0\x20\x07"
            . "\x40"
            . "\x70\x7f\xff\xff\xff"
            . "\x70\x00\x00\x00\x00"
            . "\x70\x7f\xff\xff\xff"
            . "\x70" . pack('N', $handle)
            . "\x70\x00\x00\x00\x00"
            . "\x70" . pack('N', $linkCredit),
            channel: $channel,
        );
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
