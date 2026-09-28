<?php

declare(strict_types=1);

namespace Sigbits\Amqp\Tests\Transport;

use PHPUnit\Framework\TestCase;
use Sigbits\Amqp\Transport\ConnectionUri;
use Sigbits\Amqp\Transport\StreamConnector;

final class StreamConnectorTest extends TestCase
{
    public function testConnectsPlainAmqpUriWithTcpStreamTarget(): void
    {
        $captured = null;
        $connector = new StreamConnector(
            streamFactory: static function (string $target, float $timeoutSeconds, array $contextOptions) use (&$captured): mixed {
                $captured = [
                    'target' => $target,
                    'timeoutSeconds' => $timeoutSeconds,
                    'contextOptions' => $contextOptions,
                ];

                return fopen('php://temp', 'r+');
            },
        );

        $stream = $connector->connect(ConnectionUri::parse('amqp://broker.example.test'), timeoutSeconds: 2.5);

        self::assertSame('stream', get_resource_type($stream));
        self::assertSame([
            'target' => 'tcp://broker.example.test:5672',
            'timeoutSeconds' => 2.5,
            'contextOptions' => [],
        ], $captured);
    }

    public function testConnectsAmqpsUriWithTlsStreamTargetAndContextOptions(): void
    {
        $captured = null;
        $connector = new StreamConnector(
            streamFactory: static function (string $target, float $timeoutSeconds, array $contextOptions) use (&$captured): mixed {
                $captured = [
                    'target' => $target,
                    'timeoutSeconds' => $timeoutSeconds,
                    'contextOptions' => $contextOptions,
                ];

                return fopen('php://temp', 'r+');
            },
        );

        $stream = $connector->connect(ConnectionUri::parse('amqps://broker.example.test'), timeoutSeconds: 1.0);

        self::assertSame('stream', get_resource_type($stream));
        self::assertSame([
            'target' => 'tls://broker.example.test:5671',
            'timeoutSeconds' => 1.0,
            'contextOptions' => [
                'ssl' => [
                    'verify_peer' => true,
                    'verify_peer_name' => true,
                    'peer_name' => 'broker.example.test',
                ],
            ],
        ], $captured);
    }
}
