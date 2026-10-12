<?php

declare(strict_types=1);

namespace Sigbits\Amqp\Tests\Protocol\Codec;

use PHPUnit\Framework\TestCase;
use Sigbits\Amqp\Protocol\Codec\DecodeException;
use Sigbits\Amqp\Protocol\Codec\Decoder;
use Sigbits\Amqp\Protocol\Type\Binary;
use Sigbits\Amqp\Protocol\Type\Byte;
use Sigbits\Amqp\Protocol\Type\Float32;
use Sigbits\Amqp\Protocol\Type\Float64;
use Sigbits\Amqp\Protocol\Type\Int_;
use Sigbits\Amqp\Protocol\Type\Long_;
use Sigbits\Amqp\Protocol\Type\Short;
use Sigbits\Amqp\Protocol\Type\Symbol;
use Sigbits\Amqp\Protocol\Type\Timestamp;
use Sigbits\Amqp\Protocol\Type\UByte;
use Sigbits\Amqp\Protocol\Type\UInt;
use Sigbits\Amqp\Protocol\Type\ULong;
use Sigbits\Amqp\Protocol\Type\UShort;
use Sigbits\Amqp\Protocol\Type\Uuid;

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

    public function testDecodesULongZeroCompactEncoding(): void
    {
        $decoder = new Decoder();

        self::assertEquals(new ULong(0), $decoder->decode("\x44"));
    }

    public function testDecodesSmallULongMaximum(): void
    {
        $decoder = new Decoder();

        self::assertEquals(new ULong(255), $decoder->decode("\x53\xff"));
    }

    public function testDecodesULongMaximum(): void
    {
        $decoder = new Decoder();

        self::assertEquals(new ULong('18446744073709551615'), $decoder->decode("\x80\xff\xff\xff\xff\xff\xff\xff\xff"));
    }

    public function testRejectsTruncatedSmallULong(): void
    {
        $decoder = new Decoder();

        $this->expectException(DecodeException::class);
        $this->expectExceptionMessage('Truncated AMQP ulong value.');

        $decoder->decode("\x53");
    }

    public function testRejectsTruncatedULong(): void
    {
        $decoder = new Decoder();

        $this->expectException(DecodeException::class);
        $this->expectExceptionMessage('Truncated AMQP ulong value.');

        $decoder->decode("\x80\x00\x00\x00\x00\x00\x00\x00");
    }

    public function testDecodesFloat32(): void
    {
        $decoder = new Decoder();

        self::assertEquals(new Float32(1.5), $decoder->decode("\x72\x3f\xc0\x00\x00"));
    }

    public function testRejectsTruncatedFloat32(): void
    {
        $decoder = new Decoder();

        $this->expectException(DecodeException::class);
        $this->expectExceptionMessage('Truncated AMQP float value.');

        $decoder->decode("\x72\x3f\xc0\x00");
    }

    public function testDecodesFloat64(): void
    {
        $decoder = new Decoder();

        self::assertEquals(new Float64(1.5), $decoder->decode("\x82\x3f\xf8\x00\x00\x00\x00\x00\x00"));
    }

    public function testRejectsTruncatedFloat64(): void
    {
        $decoder = new Decoder();

        $this->expectException(DecodeException::class);
        $this->expectExceptionMessage('Truncated AMQP double value.');

        $decoder->decode("\x82\x3f\xf8\x00\x00\x00\x00\x00");
    }

    public function testDecodesTimestamp(): void
    {
        $decoder = new Decoder();

        self::assertEquals(new Timestamp(1), $decoder->decode("\x83\x00\x00\x00\x00\x00\x00\x00\x01"));
    }

    public function testRejectsTruncatedTimestamp(): void
    {
        $decoder = new Decoder();

        $this->expectException(DecodeException::class);
        $this->expectExceptionMessage('Truncated AMQP timestamp value.');

        $decoder->decode("\x83\x00\x00\x00\x00\x00\x00\x00");
    }

    public function testDecodesUuid(): void
    {
        $decoder = new Decoder();

        self::assertEquals(
            new Uuid('00112233-4455-6677-8899-aabbccddeeff'),
            $decoder->decode("\x98\x00\x11\x22\x33\x44\x55\x66\x77\x88\x99\xaa\xbb\xcc\xdd\xee\xff"),
        );
    }

    public function testRejectsTruncatedUuid(): void
    {
        $decoder = new Decoder();

        $this->expectException(DecodeException::class);
        $this->expectExceptionMessage('Truncated AMQP uuid value.');

        $decoder->decode("\x98\x00\x11\x22\x33\x44\x55\x66\x77\x88\x99\xaa\xbb\xcc\xdd\xee");
    }

    public function testDecodesBinary8(): void
    {
        $decoder = new Decoder();

        self::assertEquals(new Binary('orders'), $decoder->decode("\xa0\x06orders"));
    }

    public function testDecodesBinary32(): void
    {
        $decoder = new Decoder();
        $bytes = str_repeat('x', 256);

        self::assertEquals(new Binary($bytes), $decoder->decode("\xb0\x00\x00\x01\x00" . $bytes));
    }

    public function testRejectsTruncatedBinary8(): void
    {
        $decoder = new Decoder();

        $this->expectException(DecodeException::class);
        $this->expectExceptionMessage('Truncated AMQP binary value.');

        $decoder->decode("\xa0\x06ord");
    }

    public function testRejectsTruncatedBinary32(): void
    {
        $decoder = new Decoder();

        $this->expectException(DecodeException::class);
        $this->expectExceptionMessage('Truncated AMQP binary value.');

        $decoder->decode("\xb0\x00\x00\x00");
    }

    public function testDecodesString8(): void
    {
        $decoder = new Decoder();

        self::assertSame('orders', $decoder->decode("\xa1\x06orders"));
    }

    public function testDecodesString32(): void
    {
        $decoder = new Decoder();
        $text = str_repeat('x', 256);

        self::assertSame($text, $decoder->decode("\xb1\x00\x00\x01\x00" . $text));
    }

    public function testRejectsTruncatedString8(): void
    {
        $decoder = new Decoder();

        $this->expectException(DecodeException::class);
        $this->expectExceptionMessage('Truncated AMQP string value.');

        $decoder->decode("\xa1\x06ord");
    }

    public function testRejectsTruncatedString32(): void
    {
        $decoder = new Decoder();

        $this->expectException(DecodeException::class);
        $this->expectExceptionMessage('Truncated AMQP string value.');

        $decoder->decode("\xb1\x00\x00\x00");
    }

    public function testDecodesSymbol8(): void
    {
        $decoder = new Decoder();

        self::assertEquals(new Symbol('orders'), $decoder->decode("\xa3\x06orders"));
    }

    public function testDecodesSymbol32(): void
    {
        $decoder = new Decoder();
        $text = str_repeat('x', 256);

        self::assertEquals(new Symbol($text), $decoder->decode("\xb3\x00\x00\x01\x00" . $text));
    }

    public function testRejectsTruncatedSymbol8(): void
    {
        $decoder = new Decoder();

        $this->expectException(DecodeException::class);
        $this->expectExceptionMessage('Truncated AMQP symbol value.');

        $decoder->decode("\xa3\x06ord");
    }

    public function testRejectsTruncatedSymbol32(): void
    {
        $decoder = new Decoder();

        $this->expectException(DecodeException::class);
        $this->expectExceptionMessage('Truncated AMQP symbol value.');

        $decoder->decode("\xb3\x00\x00\x00");
    }

    public function testRejectsUnsupportedFormatCode(): void
    {
        $decoder = new Decoder();

        $this->expectException(DecodeException::class);
        $this->expectExceptionMessage('Unsupported AMQP format code 0xFF.');

        $decoder->decode("\xff");
    }
}
