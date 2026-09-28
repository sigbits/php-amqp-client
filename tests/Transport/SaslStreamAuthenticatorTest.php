<?php

declare(strict_types=1);

namespace Sigbits\Amqp\Tests\Transport;

use PHPUnit\Framework\TestCase;
use Sigbits\Amqp\Protocol\Sasl\SaslClient;
use Sigbits\Amqp\Protocol\Sasl\SaslException;
use Sigbits\Amqp\Transport\SaslStreamAuthenticator;

final class SaslStreamAuthenticatorTest extends TestCase
{
    public function testAuthenticatesAnonymousClientOverSaslFrames(): void
    {
        $incoming = "AMQP\x03\x01\x00\x00"
            . "\x00\x00\x00\x1c\x02\x01\x00\x00\x00\x53\x40\xc0\x0f\x01\xe0\x0c\x01\xa3\x09ANONYMOUS"
            . "\x00\x00\x00\x10\x02\x01\x00\x00\x00\x53\x44\xc0\x03\x01\x50\x00";
        $written = '';
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

        $authenticator->authenticate(fopen('php://temp', 'r+'), SaslClient::anonymous());

        self::assertSame(
            "AMQP\x03\x01\x00\x00"
                . "\x00\x00\x00\x19\x02\x01\x00\x00\x00\x53\x41\xc0\x0c\x01\xa3\x09ANONYMOUS",
            $written,
        );
    }

    public function testRejectsFailedSaslOutcomeFromStream(): void
    {
        $incoming = "AMQP\x03\x01\x00\x00"
            . "\x00\x00\x00\x1c\x02\x01\x00\x00\x00\x53\x40\xc0\x0f\x01\xe0\x0c\x01\xa3\x09ANONYMOUS"
            . "\x00\x00\x00\x10\x02\x01\x00\x00\x00\x53\x44\xc0\x03\x01\x50\x01";
        $authenticator = new SaslStreamAuthenticator(
            reader: static function (mixed $stream, int $length) use (&$incoming): string {
                $chunk = substr($incoming, 0, $length);
                $incoming = substr($incoming, $length);

                return $chunk;
            },
            writer: static fn (mixed $stream, string $bytes): int => strlen($bytes),
        );

        $this->expectException(SaslException::class);
        $this->expectExceptionMessage('AMQP SASL authentication failed with AUTH outcome.');

        $authenticator->authenticate(fopen('php://temp', 'r+'), SaslClient::anonymous());
    }
}
