<?php

declare(strict_types=1);

namespace Sigbits\Amqp\Tests\Transport;

use PHPUnit\Framework\TestCase;
use Sigbits\Amqp\Protocol\Sasl\SaslClient;
use Sigbits\Amqp\Transport\ConnectionUri;
use Sigbits\Amqp\Transport\SaslStreamAuthenticator;
use Sigbits\Amqp\Transport\SaslStreamConnector;
use Sigbits\Amqp\Transport\StreamConnector;

final class SaslStreamConnectorTest extends TestCase
{
    public function testConnectsStreamAndRunsSaslAuthentication(): void
    {
        $incoming = "AMQP\x03\x01\x00\x00"
            . "\x00\x00\x00\x1c\x02\x01\x00\x00\x00\x53\x40\xc0\x0f\x01\xe0\x0c\x01\xa3\x09ANONYMOUS"
            . "\x00\x00\x00\x10\x02\x01\x00\x00\x00\x53\x44\xc0\x03\x01\x50\x00";
        $capturedTarget = null;
        $written = '';
        $streamConnector = new StreamConnector(
            streamFactory: static function (string $target, float $timeoutSeconds, array $contextOptions) use (&$capturedTarget): mixed {
                $capturedTarget = $target;

                return fopen('php://temp', 'r+');
            },
        );
        $authenticator = new SaslStreamAuthenticator(
            reader: static function (mixed $stream, int $length) use (&$incoming): string {
                $chunk = substr($incoming, 0, $length);
                $incoming = substr($incoming, $length);

                return $chunk;
            },
            writer: static function (mixed $stream, string $bytes) use (&$written): int {
                $written .= $bytes;

                return strlen($bytes);
            },
        );
        $connector = new SaslStreamConnector($streamConnector, $authenticator);

        $stream = $connector->connect(
            ConnectionUri::parse('amqps://broker.example.test'),
            SaslClient::anonymous(),
        );

        self::assertSame('stream', get_resource_type($stream));
        self::assertSame('tls://broker.example.test:5671', $capturedTarget);
        self::assertSame(
            "AMQP\x03\x01\x00\x00"
                . "\x00\x00\x00\x19\x02\x01\x00\x00\x00\x53\x41\xc0\x0c\x01\xa3\x09ANONYMOUS",
            $written,
        );
    }
}
