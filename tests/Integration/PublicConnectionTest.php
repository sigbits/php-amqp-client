<?php

declare(strict_types=1);

namespace Sigbits\Amqp\Tests\Integration;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Sigbits\Amqp\Client\Connection;
use Sigbits\Amqp\Engine\ConnectionState;
use Sigbits\Amqp\Engine\ReceiverLinkState;
use Sigbits\Amqp\Engine\SenderLinkState;
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

    public function testPublicSenderSendsMessageAgainstQpid(): void
    {
        if (getenv('RUN_BROKER_TESTS') !== '1') {
            self::markTestSkipped('Set RUN_BROKER_TESTS=1 to run broker integration tests.');
        }

        $address = 'sigbits.public.sender.' . bin2hex(random_bytes(4));
        $this->createQpidQueue($address);

        $connection = Connection::connect(getenv('AMQP_QPID_URI') ?: 'amqp://guest:guest@qpid:5672', timeoutSeconds: 5.0);
        $session = $connection->beginSession();
        $sender = $session->openSender($address);
        $creditBeforeSend = $sender->availableCredit();

        $sender->send('hello qpid');

        self::assertGreaterThan(0, $creditBeforeSend);
        self::assertSame($creditBeforeSend - 1, $sender->availableCredit());

        $session->end();
        $connection->close();
    }

    public function testPublicSenderSendsMessageAgainstRabbitMq(): void
    {
        if (getenv('RUN_BROKER_TESTS') !== '1') {
            self::markTestSkipped('Set RUN_BROKER_TESTS=1 to run broker integration tests.');
        }

        $queue = 'sigbits.public.sender.' . bin2hex(random_bytes(4));
        $this->createRabbitMqQueue($queue);

        $connection = Connection::connect(getenv('AMQP_RABBITMQ_URI') ?: 'amqp://guest:guest@rabbitmq:5672', timeoutSeconds: 5.0);
        $session = $connection->beginSession();
        $sender = $session->openSender('/queues/' . rawurlencode($queue));
        $creditBeforeSend = $sender->availableCredit();

        $sender->send('hello rabbitmq');

        self::assertGreaterThan(0, $creditBeforeSend);
        self::assertSame($creditBeforeSend - 1, $sender->availableCredit());

        $session->end();
        $connection->close();
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

    public function testPublicReceiverReceivesMessageAgainstQpid(): void
    {
        if (getenv('RUN_BROKER_TESTS') !== '1') {
            self::markTestSkipped('Set RUN_BROKER_TESTS=1 to run broker integration tests.');
        }

        $address = 'sigbits.public.receiver.' . bin2hex(random_bytes(4));
        $this->createQpidQueue($address);

        $connection = Connection::connect(getenv('AMQP_QPID_URI') ?: 'amqp://guest:guest@qpid:5672', timeoutSeconds: 5.0);
        $session = $connection->beginSession();
        $sender = $session->openSender($address);

        $sender->send('hello qpid receiver');
        $session->end();
        $connection->close();

        $connection = Connection::connect(getenv('AMQP_QPID_URI') ?: 'amqp://guest:guest@qpid:5672', timeoutSeconds: 5.0);
        $session = $connection->beginSession();
        $receiver = $session->openReceiver($address);
        $message = $receiver->receive(timeoutMilliseconds: 5000);

        self::assertNotNull($message);
        self::assertSame('hello qpid receiver', $message->body);

        $session->end();
        $connection->close();
    }

    public function testPublicReceiverReceivesMessageAgainstRabbitMq(): void
    {
        if (getenv('RUN_BROKER_TESTS') !== '1') {
            self::markTestSkipped('Set RUN_BROKER_TESTS=1 to run broker integration tests.');
        }

        $queue = 'sigbits.public.receiver.' . bin2hex(random_bytes(4));
        $this->createRabbitMqQueue($queue);
        $address = '/queues/' . rawurlencode($queue);

        $connection = Connection::connect(getenv('AMQP_RABBITMQ_URI') ?: 'amqp://guest:guest@rabbitmq:5672', timeoutSeconds: 5.0);
        $session = $connection->beginSession();
        $sender = $session->openSender($address);

        $sender->send('hello rabbitmq receiver');
        $session->end();
        $connection->close();

        $connection = Connection::connect(getenv('AMQP_RABBITMQ_URI') ?: 'amqp://guest:guest@rabbitmq:5672', timeoutSeconds: 5.0);
        $session = $connection->beginSession();
        $receiver = $session->openReceiver($address);
        $message = $receiver->receive(timeoutMilliseconds: 5000);

        self::assertNotNull($message);
        self::assertSame('hello rabbitmq receiver', $message->body);

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

    public function testPublicReceiverAcceptsDeliveryAgainstQpid(): void
    {
        if (getenv('RUN_BROKER_TESTS') !== '1') {
            self::markTestSkipped('Set RUN_BROKER_TESTS=1 to run broker integration tests.');
        }

        $address = 'sigbits.public.accept.' . bin2hex(random_bytes(4));
        $this->createQpidQueue($address);

        $connection = Connection::connect(getenv('AMQP_QPID_URI') ?: 'amqp://guest:guest@qpid:5672', timeoutSeconds: 5.0);
        $session = $connection->beginSession();
        $sender = $session->openSender($address);

        $sender->send('hello qpid accepted delivery');
        $session->end();
        $connection->close();

        $connection = Connection::connect(getenv('AMQP_QPID_URI') ?: 'amqp://guest:guest@qpid:5672', timeoutSeconds: 5.0);
        $session = $connection->beginSession();
        $receiver = $session->openReceiver($address);
        $delivery = $receiver->receiveDelivery(timeoutMilliseconds: 5000);

        self::assertNotNull($delivery);
        self::assertSame('hello qpid accepted delivery', $delivery->message()->body);

        $delivery->accept();

        $session->end();
        $connection->close();
    }

    public function testPublicReceiverAcceptsDeliveryAgainstRabbitMq(): void
    {
        if (getenv('RUN_BROKER_TESTS') !== '1') {
            self::markTestSkipped('Set RUN_BROKER_TESTS=1 to run broker integration tests.');
        }

        $queue = 'sigbits.public.accept.' . bin2hex(random_bytes(4));
        $this->createRabbitMqQueue($queue);
        $address = '/queues/' . rawurlencode($queue);

        $connection = Connection::connect(getenv('AMQP_RABBITMQ_URI') ?: 'amqp://guest:guest@rabbitmq:5672', timeoutSeconds: 5.0);
        $session = $connection->beginSession();
        $sender = $session->openSender($address);

        $sender->send('hello rabbitmq accepted delivery');
        $session->end();
        $connection->close();

        $connection = Connection::connect(getenv('AMQP_RABBITMQ_URI') ?: 'amqp://guest:guest@rabbitmq:5672', timeoutSeconds: 5.0);
        $session = $connection->beginSession();
        $receiver = $session->openReceiver($address);
        $delivery = $receiver->receiveDelivery(timeoutMilliseconds: 5000);

        self::assertNotNull($delivery);
        self::assertSame('hello rabbitmq accepted delivery', $delivery->message()->body);

        $delivery->accept();

        $session->end();
        $connection->close();

        self::assertSame(0, $this->rabbitMqReadyMessageCount($queue));
    }

    public function testPublicSenderDetachesAgainstArtemis(): void
    {
        if (getenv('RUN_BROKER_TESTS') !== '1') {
            self::markTestSkipped('Set RUN_BROKER_TESTS=1 to run broker integration tests.');
        }

        $connection = Connection::connect(getenv('AMQP_ARTEMIS_URI') ?: 'amqp://guest:guest@artemis:5672', timeoutSeconds: 5.0);
        $session = $connection->beginSession();
        $sender = $session->openSender('sigbits.public.sender.detach.' . bin2hex(random_bytes(4)));

        $sender->detach();

        self::assertSame(SenderLinkState::Detached, $sender->state());

        $session->end();
        $connection->close();
    }

    public function testPublicSenderDetachesAgainstQpid(): void
    {
        if (getenv('RUN_BROKER_TESTS') !== '1') {
            self::markTestSkipped('Set RUN_BROKER_TESTS=1 to run broker integration tests.');
        }

        $address = 'sigbits.public.sender.detach.' . bin2hex(random_bytes(4));
        $this->createQpidQueue($address);

        $connection = Connection::connect(getenv('AMQP_QPID_URI') ?: 'amqp://guest:guest@qpid:5672', timeoutSeconds: 5.0);
        $session = $connection->beginSession();
        $sender = $session->openSender($address);

        $sender->detach();

        self::assertSame(SenderLinkState::Detached, $sender->state());

        $session->end();
        $connection->close();
    }

    public function testPublicReceiverDetachesAgainstArtemis(): void
    {
        if (getenv('RUN_BROKER_TESTS') !== '1') {
            self::markTestSkipped('Set RUN_BROKER_TESTS=1 to run broker integration tests.');
        }

        $connection = Connection::connect(getenv('AMQP_ARTEMIS_URI') ?: 'amqp://guest:guest@artemis:5672', timeoutSeconds: 5.0);
        $session = $connection->beginSession();
        $receiver = $session->openReceiver('sigbits.public.receiver.detach.' . bin2hex(random_bytes(4)));

        $receiver->detach();

        self::assertSame(ReceiverLinkState::Detached, $receiver->state());

        $session->end();
        $connection->close();
    }

    public function testPublicReceiverDetachesAgainstQpid(): void
    {
        if (getenv('RUN_BROKER_TESTS') !== '1') {
            self::markTestSkipped('Set RUN_BROKER_TESTS=1 to run broker integration tests.');
        }

        $address = 'sigbits.public.receiver.detach.' . bin2hex(random_bytes(4));
        $this->createQpidQueue($address);

        $connection = Connection::connect(getenv('AMQP_QPID_URI') ?: 'amqp://guest:guest@qpid:5672', timeoutSeconds: 5.0);
        $session = $connection->beginSession();
        $receiver = $session->openReceiver($address);

        $receiver->detach();

        self::assertSame(ReceiverLinkState::Detached, $receiver->state());

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
            'rabbitmq' => [getenv('AMQP_RABBITMQ_URI') ?: 'amqp://guest:guest@rabbitmq:5672'],
        ];
    }

    private function createQpidQueue(string $name): void
    {
        $body = json_encode([
            'type' => 'standard',
            'durable' => false,
        ]);

        if ($body === false) {
            self::fail('Could not encode Qpid queue creation payload.');
        }

        $context = stream_context_create([
            'http' => [
                'method' => 'PUT',
                'header' => 'Authorization: Basic ' . base64_encode('guest:guest') . "\r\n"
                    . "Content-Type: application/json\r\n",
                'content' => $body,
                'ignore_errors' => true,
            ],
        ]);
        $response = file_get_contents(
            'http://qpid:8080/api/latest/queue/default/default/' . rawurlencode($name),
            false,
            $context,
        );
        $status = $http_response_header[0] ?? '';

        if ($response === false || !str_contains($status, '201 Created')) {
            self::fail('Could not create Qpid test queue: ' . $status);
        }
    }

    private function createRabbitMqQueue(string $name): void
    {
        $body = json_encode([
            'durable' => true,
            'arguments' => new \stdClass(),
        ]);

        if ($body === false) {
            self::fail('Could not encode RabbitMQ queue creation payload.');
        }

        $context = stream_context_create([
            'http' => [
                'method' => 'PUT',
                'header' => 'Authorization: Basic ' . base64_encode('guest:guest') . "\r\n"
                    . "Content-Type: application/json\r\n",
                'content' => $body,
                'ignore_errors' => true,
            ],
        ]);
        $response = file_get_contents(
            'http://rabbitmq:15672/api/queues/%2F/' . rawurlencode($name),
            false,
            $context,
        );
        $status = $http_response_header[0] ?? '';

        if ($response === false || (!str_contains($status, '201 Created') && !str_contains($status, '204 No Content'))) {
            self::fail('Could not create RabbitMQ test queue: ' . $status);
        }
    }

    private function rabbitMqReadyMessageCount(string $name): int
    {
        $body = json_encode([
            'count' => 1,
            'ackmode' => 'ack_requeue_true',
            'encoding' => 'auto',
            'truncate' => 50_000,
        ]);

        if ($body === false) {
            self::fail('Could not encode RabbitMQ queue get payload.');
        }

        $context = stream_context_create([
            'http' => [
                'method' => 'POST',
                'header' => 'Authorization: Basic ' . base64_encode('guest:guest') . "\r\n"
                    . "Content-Type: application/json\r\n",
                'content' => $body,
                'ignore_errors' => true,
            ],
        ]);
        $response = file_get_contents(
            'http://rabbitmq:15672/api/queues/%2F/' . rawurlencode($name) . '/get',
            false,
            $context,
        );
        $status = $http_response_header[0] ?? '';

        if ($response === false || !str_contains($status, '200 OK')) {
            self::fail('Could not inspect RabbitMQ test queue messages: ' . $status);
        }

        $messages = json_decode($response, associative: true);

        if (!is_array($messages)) {
            self::fail('RabbitMQ queue get response did not contain a message list.');
        }

        return count($messages);
    }
}
