<?php

declare(strict_types=1);

namespace Sigbits\Amqp\Tests\Protocol\Codec;

use PHPUnit\Framework\TestCase;
use Sigbits\Amqp\Protocol\Codec\DecodeException;
use Sigbits\Amqp\Protocol\Codec\Decoder;
use Sigbits\Amqp\Protocol\Type\UByte;
use Sigbits\Amqp\Protocol\Type\UInt;
use Sigbits\Amqp\Protocol\Type\UShort;

final class DecoderTest extends TestCase
{
    public function testDecodesNullEncodingAsNull(): void
    {
        $decoder = new Decoder();

        self::assertNull($decoder->decode("\x40"));
    }

    public function testDecodesTrueEncodingAsTrue(): void
    {
        $decoder = new Decoder();

        self::assertTrue($decoder->decode("\x41"));
    }

    public function testDecodesFalseEncodingAsFalse(): void
    {
        $decoder = new Decoder();

        self::assertFalse($decoder->decode("\x42"));
    }

    public function testDecodesBooleanConstructorWithFalsePayloadAsFalse(): void
    {
        $decoder = new Decoder();

        self::assertFalse($decoder->decode("\x56\x00"));
    }

    public function testDecodesBooleanConstructorWithTruePayloadAsTrue(): void
    {
        $decoder = new Decoder();

        self::assertTrue($decoder->decode("\x56\x01"));
    }

    public function testRejectsTruncatedBooleanConstructor(): void
    {
        $decoder = new Decoder();

        $this->expectException(DecodeException::class);
        $this->expectExceptionMessage('Truncated AMQP boolean value.');

        $decoder->decode("\x56");
    }

    public function testDecodesUByteZero(): void
    {
        $decoder = new Decoder();

        self::assertEquals(new UByte(0), $decoder->decode("\x50\x00"));
    }

    public function testDecodesUByteMaximum(): void
    {
        $decoder = new Decoder();

        self::assertEquals(new UByte(255), $decoder->decode("\x50\xff"));
    }

    public function testRejectsTruncatedUByte(): void
    {
        $decoder = new Decoder();

        $this->expectException(DecodeException::class);
        $this->expectExceptionMessage('Truncated AMQP ubyte value.');

        $decoder->decode("\x50");
    }

    public function testDecodesUShortZero(): void
    {
        $decoder = new Decoder();

        self::assertEquals(new UShort(0), $decoder->decode("\x60\x00\x00"));
    }

    public function testDecodesUShortMaximum(): void
    {
        $decoder = new Decoder();

        self::assertEquals(new UShort(65535), $decoder->decode("\x60\xff\xff"));
    }

    public function testRejectsTruncatedUShort(): void
    {
        $decoder = new Decoder();

        $this->expectException(DecodeException::class);
        $this->expectExceptionMessage('Truncated AMQP ushort value.');

        $decoder->decode("\x60\x00");
    }

    public function testDecodesUIntZero(): void
    {
        $decoder = new Decoder();

        self::assertEquals(new UInt(0), $decoder->decode("\x70\x00\x00\x00\x00"));
    }

    public function testDecodesUIntZeroCompactEncoding(): void
    {
        $decoder = new Decoder();

        self::assertEquals(new UInt(0), $decoder->decode("\x43"));
    }

    public function testDecodesSmallUIntMaximum(): void
    {
        $decoder = new Decoder();

        self::assertEquals(new UInt(255), $decoder->decode("\x52\xff"));
    }

    public function testRejectsTruncatedSmallUInt(): void
    {
        $decoder = new Decoder();

        $this->expectException(DecodeException::class);
        $this->expectExceptionMessage('Truncated AMQP uint value.');

        $decoder->decode("\x52");
    }

    public function testDecodesUIntMaximum(): void
    {
        $decoder = new Decoder();

        self::assertEquals(new UInt(4294967295), $decoder->decode("\x70\xff\xff\xff\xff"));
    }

    public function testRejectsTruncatedUInt(): void
    {
        $decoder = new Decoder();

        $this->expectException(DecodeException::class);
        $this->expectExceptionMessage('Truncated AMQP uint value.');

        $decoder->decode("\x70\x00\x00\x00");
    }

    public function testRejectsUnsupportedFormatCode(): void
    {
        $decoder = new Decoder();

        $this->expectException(DecodeException::class);
        $this->expectExceptionMessage('Unsupported AMQP format code 0x44.');

        $decoder->decode("\x44");
    }
}
