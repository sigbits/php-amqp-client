<?php

declare(strict_types=1);

namespace Sigbits\Amqp\Tests\Integration;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Sigbits\Amqp\Client\ClientException;
use Sigbits\Amqp\Client\Connection;
use Sigbits\Amqp\Engine\ConnectionState;
use Sigbits\Amqp\Engine\SessionState;

final class PublicConnectionTest extends TestCase
{
    #[DataProvider('brokerProvider')]
    public function testConnectOpensPublicConnectionAgainstBroker(string $uri): void
    {
        if (getenv('RUN_BROKER_TESTS') !== '1') {
            self::markTestSkipped('Set RUN_BROKER_TESTS=1 to run broker integration tests.');
        }

        $connection = Connection::connect($uri, timeoutSeconds: 5.0);

        self::assertSame(ConnectionState::Opened, $connection->state());

        $connection->close();
    }

    #[DataProvider('brokerProvider')]
    public function testBeginSessionMapsPublicSessionAgainstBroker(string $uri): void
    {
        if (getenv('RUN_BROKER_TESTS') !== '1') {
            self::markTestSkipped('Set RUN_BROKER_TESTS=1 to run broker integration tests.');
        }

        $connection = Connection::connect($uri, timeoutSeconds: 5.0);
        $session = $connection->beginSession();

        self::assertSame(SessionState::Mapped, $session->state());

        $session->end();
        self::assertSame(SessionState::Ended, $session->state());

        $connection->close();
    }

    public function testPublicSenderSendsMessageAgainstArtemis(): void
    {
        if (getenv('RUN_BROKER_TESTS') !== '1') {
            self::markTestSkipped('Set RUN_BROKER_TESTS=1 to run broker integration tests.');
        }

        $connection = Connection::connect(getenv('AMQP_ARTEMIS_URI') ?: 'amqp://guest:guest@artemis:5672', timeoutSeconds: 5.0);
        $session = $connection->beginSession();
        $sender = $session->openSender('sigbits.public.sender.' . bin2hex(random_bytes(4)));
        $creditBeforeSend = $sender->availableCredit();

        $sender->send('hello broker');

        self::assertGreaterThan(0, $creditBeforeSend);
        self::assertSame($creditBeforeSend - 1, $sender->availableCredit());

        $session->end();
        $connection->close();
    }

    public function testPublicSenderOpenReportsQpidRemoteDetach(): void
    {
        if (getenv('RUN_BROKER_TESTS') !== '1') {
            self::markTestSkipped('Set RUN_BROKER_TESTS=1 to run broker integration tests.');
        }

        $connection = Connection::connect(getenv('AMQP_QPID_URI') ?: 'amqp://guest:guest@qpid:5672', timeoutSeconds: 5.0);
        $session = $connection->beginSession();

        $this->expectException(ClientException::class);
        $this->expectExceptionMessage('Cannot open AMQP sender link because the remote peer detached it.');

        $session->openSender('sigbits.public.sender.' . bin2hex(random_bytes(4)));
    }

    public function testPublicReceiverReceivesMessageAgainstArtemis(): void
    {
        if (getenv('RUN_BROKER_TESTS') !== '1') {
            self::markTestSkipped('Set RUN_BROKER_TESTS=1 to run broker integration tests.');
        }

        $address = 'sigbits.public.receiver.' . bin2hex(random_bytes(4));
        $connection = Connection::connect(getenv('AMQP_ARTEMIS_URI') ?: 'amqp://guest:guest@artemis:5672', timeoutSeconds: 5.0);
        $session = $connection->beginSession();
        $receiver = $session->openReceiver($address);
        $sender = $session->openSender($address);

        $sender->send('hello public receiver');
        $message = $receiver->receive(timeoutMilliseconds: 5000);

        self::assertNotNull($message);
        self::assertSame('hello public receiver', $message->body);

        $session->end();
        $connection->close();
    }

    public function testPublicReceiverAcceptsDeliveryAgainstArtemis(): void
    {
        if (getenv('RUN_BROKER_TESTS') !== '1') {
            self::markTestSkipped('Set RUN_BROKER_TESTS=1 to run broker integration tests.');
        }

        $address = 'sigbits.public.accept.' . bin2hex(random_bytes(4));
        $connection = Connection::connect(getenv('AMQP_ARTEMIS_URI') ?: 'amqp://guest:guest@artemis:5672', timeoutSeconds: 5.0);
        $session = $connection->beginSession();
        $receiver = $session->openReceiver($address);
        $sender = $session->openSender($address);

        $sender->send('hello accepted delivery');
        $delivery = $receiver->receiveDelivery(timeoutMilliseconds: 5000);

        self::assertNotNull($delivery);
        self::assertSame('hello accepted delivery', $delivery->message()->body);

        $delivery->accept();

        $session->end();
        $connection->close();
    }

    /**
     * @return array<string, array{string}>
     */
    public static function brokerProvider(): array
    {
        return [
            'qpid' => [getenv('AMQP_QPID_URI') ?: 'amqp://guest:guest@qpid:5672'],
            'artemis' => [getenv('AMQP_ARTEMIS_URI') ?: 'amqp://guest:guest@artemis:5672'],
        ];
    }
}
