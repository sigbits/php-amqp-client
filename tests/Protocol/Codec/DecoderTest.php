<?php

declare(strict_types=1);

namespace Sigbits\Amqp\Tests\Protocol\Codec;

use PHPUnit\Framework\TestCase;
use Sigbits\Amqp\Protocol\Codec\DecodeException;
use Sigbits\Amqp\Protocol\Codec\Decoder;
use Sigbits\Amqp\Protocol\Type\Byte;
use Sigbits\Amqp\Protocol\Type\Int_;
use Sigbits\Amqp\Protocol\Type\Long_;
use Sigbits\Amqp\Protocol\Type\Short;
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

    public function testDecodesByteMinimum(): void
    {
        $decoder = new Decoder();

        self::assertEquals(new Byte(-128), $decoder->decode("\x51\x80"));
    }

    public function testDecodesByteZero(): void
    {
        $decoder = new Decoder();

        self::assertEquals(new Byte(0), $decoder->decode("\x51\x00"));
    }

    public function testDecodesByteMaximum(): void
    {
        $decoder = new Decoder();

        self::assertEquals(new Byte(127), $decoder->decode("\x51\x7f"));
    }

    public function testRejectsTruncatedByte(): void
    {
        $decoder = new Decoder();

        $this->expectException(DecodeException::class);
        $this->expectExceptionMessage('Truncated AMQP byte value.');

        $decoder->decode("\x51");
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

    public function testDecodesShortMinimum(): void
    {
        $decoder = new Decoder();

        self::assertEquals(new Short(-32768), $decoder->decode("\x61\x80\x00"));
    }

    public function testDecodesShortZero(): void
    {
        $decoder = new Decoder();

        self::assertEquals(new Short(0), $decoder->decode("\x61\x00\x00"));
    }

    public function testDecodesShortMaximum(): void
    {
        $decoder = new Decoder();

        self::assertEquals(new Short(32767), $decoder->decode("\x61\x7f\xff"));
    }

    public function testRejectsTruncatedShort(): void
    {
        $decoder = new Decoder();

        $this->expectException(DecodeException::class);
        $this->expectExceptionMessage('Truncated AMQP short value.');

        $decoder->decode("\x61\x00");
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

    public function testDecodesIntMinimum(): void
    {
        $decoder = new Decoder();

        self::assertEquals(new Int_(-2147483648), $decoder->decode("\x71\x80\x00\x00\x00"));
    }

    public function testDecodesIntZero(): void
    {
        $decoder = new Decoder();

        self::assertEquals(new Int_(0), $decoder->decode("\x71\x00\x00\x00\x00"));
    }

    public function testDecodesIntMaximum(): void
    {
        $decoder = new Decoder();

        self::assertEquals(new Int_(2147483647), $decoder->decode("\x71\x7f\xff\xff\xff"));
    }

    public function testRejectsTruncatedInt(): void
    {
        $decoder = new Decoder();

        $this->expectException(DecodeException::class);
        $this->expectExceptionMessage('Truncated AMQP int value.');

        $decoder->decode("\x71\x00\x00\x00");
    }

    public function testDecodesLongMinimum(): void
    {
        $decoder = new Decoder();

        self::assertEquals(new Long_(PHP_INT_MIN), $decoder->decode("\x81\x80\x00\x00\x00\x00\x00\x00\x00"));
    }

    public function testDecodesLongZero(): void
    {
        $decoder = new Decoder();

        self::assertEquals(new Long_(0), $decoder->decode("\x81\x00\x00\x00\x00\x00\x00\x00\x00"));
    }

    public function testDecodesLongMaximum(): void
    {
        $decoder = new Decoder();

        self::assertEquals(new Long_(PHP_INT_MAX), $decoder->decode("\x81\x7f\xff\xff\xff\xff\xff\xff\xff"));
    }

    public function testRejectsTruncatedLong(): void
    {
        $decoder = new Decoder();

        $this->expectException(DecodeException::class);
        $this->expectExceptionMessage('Truncated AMQP long value.');

        $decoder->decode("\x81\x00\x00\x00\x00\x00\x00\x00");
    }

    public function testRejectsUnsupportedFormatCode(): void
    {
        $decoder = new Decoder();

        $this->expectException(DecodeException::class);
        $this->expectExceptionMessage('Unsupported AMQP format code 0x44.');

        $decoder->decode("\x44");
    }
}
