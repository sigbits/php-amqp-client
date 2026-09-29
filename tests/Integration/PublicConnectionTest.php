<?php

declare(strict_types=1);

namespace Sigbits\Amqp\Tests\Integration;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Sigbits\Amqp\Client\Connection;
use Sigbits\Amqp\Engine\ConnectionState;

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
