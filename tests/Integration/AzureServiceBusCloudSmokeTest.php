<?php

declare(strict_types=1);

namespace Sigbits\Amqp\Tests\Integration;

use PHPUnit\Framework\TestCase;
use Sigbits\Amqp\Client\Connection;
use Sigbits\Amqp\Client\Delivery;
use Sigbits\Amqp\Client\Receiver;

final class AzureServiceBusCloudSmokeTest extends TestCase
{
    public function testSendsReceivesAndAcceptsMessageAgainstAzureServiceBusCloudQueue(): void
    {
        if (getenv('RUN_AZURE_SERVICE_BUS_CLOUD_TESTS') !== '1') {
            self::markTestSkipped('Set RUN_AZURE_SERVICE_BUS_CLOUD_TESTS=1 to run Azure Service Bus cloud smoke tests.');
        }

        $uri = getenv('AMQP_AZURE_SERVICE_BUS_CLOUD_URI');
        $address = getenv('AMQP_AZURE_SERVICE_BUS_CLOUD_ADDRESS');

        if ($uri === false || $uri === '') {
            self::fail('Set AMQP_AZURE_SERVICE_BUS_CLOUD_URI to run Azure Service Bus cloud smoke tests.');
        }

        if ($address === false || $address === '') {
            self::fail('Set AMQP_AZURE_SERVICE_BUS_CLOUD_ADDRESS to run Azure Service Bus cloud smoke tests.');
        }

        $body = 'sigbits azure cloud smoke ' . bin2hex(random_bytes(8));
        $connection = Connection::connect($uri, timeoutSeconds: 10.0);
        $session = $connection->beginSession();
        $sender = $session->openSender($address);

        $sender->send($body);
        $session->end();
        $connection->close();

        $connection = Connection::connect($uri, timeoutSeconds: 10.0);
        $session = $connection->beginSession();
        $receiver = $session->openReceiver($address, credit: 10);
        $delivery = $this->receiveDeliveryWithBody($receiver, $body);

        self::assertSame($body, $delivery->message()->body);

        $delivery->accept();

        $session->end();
        $connection->close();
    }

    private function receiveDeliveryWithBody(Receiver $receiver, string $body): Delivery
    {
        $deadline = microtime(true) + 30.0;

        do {
            $delivery = $receiver->receiveDelivery(timeoutMilliseconds: 1000);

            if ($delivery === null) {
                continue;
            }

            if ($delivery->message()->body === $body) {
                return $delivery;
            }

            $delivery->release();
            $receiver->grantCredit(1);
        } while (microtime(true) < $deadline);

        self::fail('Timed out waiting for Azure Service Bus cloud delivery body.');
    }
}
