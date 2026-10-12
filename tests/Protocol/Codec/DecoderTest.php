<?php

declare(strict_types=1);

namespace Sigbits\Amqp\Tests\Protocol\Codec;

use PHPUnit\Framework\TestCase;
use Sigbits\Amqp\Protocol\Codec\DecodeException;
use Sigbits\Amqp\Protocol\Codec\Decoder;
use Sigbits\Amqp\Protocol\Type\AmqpArray;
use Sigbits\Amqp\Protocol\Type\AmqpDescribedValue;
use Sigbits\Amqp\Protocol\Type\AmqpList;
use Sigbits\Amqp\Protocol\Type\AmqpMap;
use Sigbits\Amqp\Protocol\Type\Binary;
use Sigbits\Amqp\Protocol\Type\Byte;
use Sigbits\Amqp\Protocol\Type\Char;
use Sigbits\Amqp\Protocol\Type\Decimal128;
use Sigbits\Amqp\Protocol\Type\Decimal32;
use Sigbits\Amqp\Protocol\Type\Decimal64;
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
    public function testDecodesValueWithConsumedByteCount(): void
    {
        $decoder = new Decoder();

        self::assertSame(['hello', 7], $decoder->decodeWithConsumed("\xa1\x05hello\x40"));
    }

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

    public function testDecodesDecimal32(): void
    {
        $decoder = new Decoder();

        self::assertEquals(new Decimal32("\x01\x02\x03\x04"), $decoder->decode("\x74\x01\x02\x03\x04"));
    }

    public function testRejectsTruncatedDecimal32(): void
    {
        $decoder = new Decoder();

        $this->expectException(DecodeException::class);
        $this->expectExceptionMessage('Truncated AMQP decimal32 value.');

        $decoder->decode("\x74\x01\x02\x03");
    }

    public function testDecodesDecimal64(): void
    {
        $decoder = new Decoder();

        self::assertEquals(new Decimal64("\x01\x02\x03\x04\x05\x06\x07\x08"), $decoder->decode("\x84\x01\x02\x03\x04\x05\x06\x07\x08"));
    }

    public function testRejectsTruncatedDecimal64(): void
    {
        $decoder = new Decoder();

        $this->expectException(DecodeException::class);
        $this->expectExceptionMessage('Truncated AMQP decimal64 value.');

        $decoder->decode("\x84\x01\x02\x03\x04\x05\x06\x07");
    }

    public function testDecodesDecimal128(): void
    {
        $decoder = new Decoder();
        $bytes = "\x00\x11\x22\x33\x44\x55\x66\x77\x88\x99\xaa\xbb\xcc\xdd\xee\xff";

        self::assertEquals(new Decimal128($bytes), $decoder->decode("\x94" . $bytes));
    }

    public function testRejectsTruncatedDecimal128(): void
    {
        $decoder = new Decoder();

        $this->expectException(DecodeException::class);
        $this->expectExceptionMessage('Truncated AMQP decimal128 value.');

        $decoder->decode("\x94\x00\x11\x22\x33\x44\x55\x66\x77\x88\x99\xaa\xbb\xcc\xdd\xee");
    }

    public function testDecodesChar(): void
    {
        $decoder = new Decoder();

        self::assertEquals(new Char(0x1f600), $decoder->decode("\x73\x00\x01\xf6\x00"));
    }

    public function testRejectsTruncatedChar(): void
    {
        $decoder = new Decoder();

        $this->expectException(DecodeException::class);
        $this->expectExceptionMessage('Truncated AMQP char value.');

        $decoder->decode("\x73\x00\x01\xf6");
    }

    public function testRejectsInvalidCharCodePoint(): void
    {
        $decoder = new Decoder();

        $this->expectException(DecodeException::class);
        $this->expectExceptionMessage('Invalid AMQP char value.');

        $decoder->decode("\x73\x00\x00\xd8\x00");
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

    public function testDecodesList0(): void
    {
        $decoder = new Decoder();

        self::assertEquals(new AmqpList([]), $decoder->decode("\x45"));
    }

    public function testDecodesList8(): void
    {
        $decoder = new Decoder();

        self::assertEquals(
            new AmqpList(['orders', new UInt(42), true]),
            $decoder->decode("\xc0\x0f\x03\xa1\x06orders\x70\x00\x00\x00\x2a\x41"),
        );
    }

    public function testDecodesNestedList8(): void
    {
        $decoder = new Decoder();

        self::assertEquals(
            new AmqpList([new AmqpList(['x'])]),
            $decoder->decode("\xc0\x07\x01\xc0\x04\x01\xa1\x01x"),
        );
    }

    public function testDecodesList32(): void
    {
        $decoder = new Decoder();
        $text = str_repeat('x', 256);

        self::assertEquals(
            new AmqpList([$text]),
            $decoder->decode("\xd0\x00\x00\x01\x09\x00\x00\x00\x01\xb1\x00\x00\x01\x00" . $text),
        );
    }

    public function testRejectsTruncatedList8Header(): void
    {
        $decoder = new Decoder();

        $this->expectException(DecodeException::class);
        $this->expectExceptionMessage('Truncated AMQP list value.');

        $decoder->decode("\xc0\x01");
    }

    public function testRejectsTruncatedList8Payload(): void
    {
        $decoder = new Decoder();

        $this->expectException(DecodeException::class);
        $this->expectExceptionMessage('Truncated AMQP list value.');

        $decoder->decode("\xc0\x04\x01\xa1\x02x");
    }

    public function testRejectsMalformedList8Count(): void
    {
        $decoder = new Decoder();

        $this->expectException(DecodeException::class);
        $this->expectExceptionMessage('Malformed AMQP list value.');

        $decoder->decode("\xc0\x01\x01");
    }

    public function testRejectsTruncatedList32Header(): void
    {
        $decoder = new Decoder();

        $this->expectException(DecodeException::class);
        $this->expectExceptionMessage('Truncated AMQP list value.');

        $decoder->decode("\xd0\x00\x00\x00\x04\x00\x00\x00");
    }

    public function testDecodesMap8Empty(): void
    {
        $decoder = new Decoder();

        self::assertEquals(new AmqpMap([]), $decoder->decode("\xc1\x01\x00"));
    }

    public function testDecodesMap8(): void
    {
        $decoder = new Decoder();

        self::assertEquals(
            new AmqpMap([
                ['queue', 'orders'],
                [new Symbol('priority'), new UInt(5)],
            ]),
            $decoder->decode("\xc1\x1f\x04\xa1\x05queue\xa1\x06orders\xa3\x08priority\x70\x00\x00\x00\x05"),
        );
    }

    public function testDecodesNestedMap8(): void
    {
        $decoder = new Decoder();

        self::assertEquals(
            new AmqpMap([
                ['nested', new AmqpMap([
                    ['x', true],
                ])],
            ]),
            $decoder->decode("\xc1\x10\x02\xa1\x06nested\xc1\x05\x02\xa1\x01x\x41"),
        );
    }

    public function testDecodesMap32(): void
    {
        $decoder = new Decoder();
        $text = str_repeat('x', 256);

        self::assertEquals(
            new AmqpMap([
                ['payload', $text],
            ]),
            $decoder->decode("\xd1\x00\x00\x01\x12\x00\x00\x00\x02\xa1\x07payload\xb1\x00\x00\x01\x00" . $text),
        );
    }

    public function testRejectsTruncatedMap8Header(): void
    {
        $decoder = new Decoder();

        $this->expectException(DecodeException::class);
        $this->expectExceptionMessage('Truncated AMQP map value.');

        $decoder->decode("\xc1\x01");
    }

    public function testRejectsTruncatedMap8Payload(): void
    {
        $decoder = new Decoder();

        $this->expectException(DecodeException::class);
        $this->expectExceptionMessage('Truncated AMQP map value.');

        $decoder->decode("\xc1\x05\x02\xa1\x01x");
    }

    public function testRejectsMalformedMap8Count(): void
    {
        $decoder = new Decoder();

        $this->expectException(DecodeException::class);
        $this->expectExceptionMessage('Malformed AMQP map value.');

        $decoder->decode("\xc1\x01\x01");
    }

    public function testRejectsTruncatedMap32Header(): void
    {
        $decoder = new Decoder();

        $this->expectException(DecodeException::class);
        $this->expectExceptionMessage('Truncated AMQP map value.');

        $decoder->decode("\xd1\x00\x00\x00\x04\x00\x00\x00");
    }

    public function testDecodesEmptyArray8(): void
    {
        $decoder = new Decoder();

        self::assertEquals(new AmqpArray("\xa3", []), $decoder->decode("\xe0\x02\x00\xa3"));
    }

    public function testDecodesSymbolArray8(): void
    {
        $decoder = new Decoder();

        self::assertEquals(
            new AmqpArray("\xa3", [new Symbol('a'), new Symbol('bb')]),
            $decoder->decode("\xe0\x07\x02\xa3\x01a\x02bb"),
        );
    }

    public function testDecodesUIntArray8(): void
    {
        $decoder = new Decoder();

        self::assertEquals(
            new AmqpArray("\x70", [new UInt(1), new UInt(2)]),
            $decoder->decode("\xe0\x0a\x02\x70\x00\x00\x00\x01\x00\x00\x00\x02"),
        );
    }

    public function testDecodesNestedArray8(): void
    {
        $decoder = new Decoder();

        self::assertEquals(
            new AmqpArray("\xe0", [
                new AmqpArray("\xa3", [new Symbol('x')]),
            ]),
            $decoder->decode("\xe0\x07\x01\xe0\x04\x01\xa3\x01x"),
        );
    }

    public function testDecodesArray32(): void
    {
        $decoder = new Decoder();
        $text = str_repeat('x', 256);

        self::assertEquals(
            new AmqpArray("\xb1", [$text]),
            $decoder->decode("\xf0\x00\x00\x01\x09\x00\x00\x00\x01\xb1\x00\x00\x01\x00" . $text),
        );
    }

    public function testDecodesArray8WithDescribedElementConstructor(): void
    {
        $decoder = new Decoder();

        self::assertEquals(
            new AmqpArray("\x00\x53\x75\xc0", [
                new AmqpDescribedValue(new ULong(117), new AmqpList(['x'])),
                new AmqpDescribedValue(new ULong(117), new AmqpList(['y'])),
            ]),
            $decoder->decode("\xe0\x0f\x02\x00\x53\x75\xc0\x04\x01\xa1\x01x\x04\x01\xa1\x01y"),
        );
    }

    public function testDecodesArray32WithDescribedElementConstructor(): void
    {
        $decoder = new Decoder();
        $text = str_repeat('x', 256);

        self::assertEquals(
            new AmqpArray("\x00\xa3\x04desc\xb1", [
                new AmqpDescribedValue(new Symbol('desc'), $text),
            ]),
            $decoder->decode("\xf0\x00\x00\x01\x10\x00\x00\x00\x01\x00\xa3\x04desc\xb1\x00\x00\x01\x00" . $text),
        );
    }

    public function testRejectsTruncatedArray8DescribedElementConstructor(): void
    {
        $decoder = new Decoder();

        $this->expectException(DecodeException::class);
        $this->expectExceptionMessage('Truncated AMQP array value.');

        $decoder->decode("\xe0\x04\x00\x00\x53\x75");
    }

    public function testRejectsTruncatedArray8Header(): void
    {
        $decoder = new Decoder();

        $this->expectException(DecodeException::class);
        $this->expectExceptionMessage('Truncated AMQP array value.');

        $decoder->decode("\xe0\x02\x00");
    }

    public function testRejectsTruncatedArray8Payload(): void
    {
        $decoder = new Decoder();

        $this->expectException(DecodeException::class);
        $this->expectExceptionMessage('Truncated AMQP array value.');

        $decoder->decode("\xe0\x04\x01\xa3\x02x");
    }

    public function testRejectsMalformedArray8Size(): void
    {
        $decoder = new Decoder();

        $this->expectException(DecodeException::class);
        $this->expectExceptionMessage('Malformed AMQP array value.');

        $decoder->decode("\xe0\x01\x00");
    }

    public function testRejectsTruncatedArray32Header(): void
    {
        $decoder = new Decoder();

        $this->expectException(DecodeException::class);
        $this->expectExceptionMessage('Truncated AMQP array value.');

        $decoder->decode("\xf0\x00\x00\x00\x05\x00\x00\x00\x00");
    }

    public function testDecodesDescribedValueWithSmallULongDescriptor(): void
    {
        $decoder = new Decoder();

        self::assertEquals(
            new AmqpDescribedValue(new ULong(117), new AmqpList(['body'])),
            $decoder->decode("\x00\x53\x75\xc0\x07\x01\xa1\x04body"),
        );
    }

    public function testDecodesDescribedValueWithSymbolDescriptor(): void
    {
        $decoder = new Decoder();

        self::assertEquals(
            new AmqpDescribedValue(new Symbol('example:state'), 'ready'),
            $decoder->decode("\x00\xa3\x0dexample:state\xa1\x05ready"),
        );
    }

    public function testDecodesNestedDescribedValue(): void
    {
        $decoder = new Decoder();

        self::assertEquals(
            new AmqpDescribedValue(
                new Symbol('outer'),
                new AmqpDescribedValue(new Symbol('inner'), true),
            ),
            $decoder->decode("\x00\xa3\x05outer\x00\xa3\x05inner\x41"),
        );
    }

    public function testRejectsTruncatedDescribedValueDescriptor(): void
    {
        $decoder = new Decoder();

        $this->expectException(DecodeException::class);
        $this->expectExceptionMessage('Truncated AMQP described value.');

        $decoder->decode("\x00\x53");
    }

    public function testRejectsTruncatedDescribedValueValue(): void
    {
        $decoder = new Decoder();

        $this->expectException(DecodeException::class);
        $this->expectExceptionMessage('Truncated AMQP described value.');

        $decoder->decode("\x00\x53\x75");
    }

    public function testRejectsMalformedDescribedValueDescriptor(): void
    {
        $decoder = new Decoder();

        $this->expectException(DecodeException::class);
        $this->expectExceptionMessage('Malformed AMQP described value.');

        $decoder->decode("\x00\x70\x00\x00\x00\x01\x41");
    }

    public function testRejectsUnsupportedFormatCode(): void
    {
        $decoder = new Decoder();

        $this->expectException(DecodeException::class);
        $this->expectExceptionMessage('Unsupported AMQP format code 0xFF.');

        $decoder->decode("\xff");
    }
}
