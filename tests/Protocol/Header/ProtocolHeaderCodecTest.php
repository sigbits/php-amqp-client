<?php

declare(strict_types=1);

namespace Sigbits\Amqp\Tests\Protocol\Header;

use PHPUnit\Framework\TestCase;
use Sigbits\Amqp\Protocol\Header\ProtocolHeader;
use Sigbits\Amqp\Protocol\Header\ProtocolHeaderCodec;
use Sigbits\Amqp\Protocol\Header\ProtocolHeaderException;

final class ProtocolHeaderCodecTest extends TestCase
{
    public function testEncodesAmqp10Header(): void
    {
        $codec = new ProtocolHeaderCodec();

        self::assertSame("AMQP\x00\x01\x00\x00", $codec->encode(ProtocolHeader::amqp()));
    }

    public function testDecodesAmqp10Header(): void
    {
        $codec = new ProtocolHeaderCodec();

        self::assertEquals(ProtocolHeader::amqp(), $codec->decode("AMQP\x00\x01\x00\x00"));
    }

    public function testDecodesSasl10Header(): void
    {
        $codec = new ProtocolHeaderCodec();

        self::assertEquals(ProtocolHeader::sasl(), $codec->decode("AMQP\x03\x01\x00\x00"));
    }

    public function testRejectsTruncatedHeader(): void
    {
        $codec = new ProtocolHeaderCodec();

        $this->expectException(ProtocolHeaderException::class);
        $this->expectExceptionMessage('Truncated AMQP protocol header.');

        $codec->decode('AMQP');
    }

    public function testRejectsInvalidHeaderMagic(): void
    {
        $codec = new ProtocolHeaderCodec();

        $this->expectException(ProtocolHeaderException::class);
        $this->expectExceptionMessage('Invalid AMQP protocol header magic.');

        $codec->decode("HTTP\x00\x01\x00\x00");
    }

    public function testIdentifiesAmqp10Header(): void
    {
        self::assertTrue(ProtocolHeader::amqp()->isAmqp10());
        self::assertFalse(ProtocolHeader::sasl()->isAmqp10());
        self::assertFalse((new ProtocolHeader(ProtocolHeader::PROTOCOL_ID_AMQP, 0, 9, 1))->isAmqp10());
    }
}
