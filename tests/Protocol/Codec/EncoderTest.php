<?php

declare(strict_types=1);

namespace Sigbits\Amqp\Tests\Protocol\Codec;

use PHPUnit\Framework\TestCase;
use Sigbits\Amqp\Protocol\Codec\EncodeException;
use Sigbits\Amqp\Protocol\Codec\Encoder;
use Sigbits\Amqp\Protocol\Type\AmqpArray;
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

final class EncoderTest extends TestCase
{
    public function testEncodesNullAsNullEncoding(): void
    {
        $encoder = new Encoder();

        self::assertSame("\x40", $encoder->encode(null));
    }

    public function testEncodesTrueAsTrueEncoding(): void
    {
        $encoder = new Encoder();

        self::assertSame("\x41", $encoder->encode(true));
    }

    public function testEncodesFalseAsFalseEncoding(): void
    {
        $encoder = new Encoder();

        self::assertSame("\x42", $encoder->encode(false));
    }

    public function testEncodesUByteZero(): void
    {
        $encoder = new Encoder();

        self::assertSame("\x50\x00", $encoder->encode(new UByte(0)));
    }

    public function testEncodesUByteMaximum(): void
    {
        $encoder = new Encoder();

        self::assertSame("\x50\xff", $encoder->encode(new UByte(255)));
    }

    public function testEncodesByteMinimum(): void
    {
        $encoder = new Encoder();

        self::assertSame("\x51\x80", $encoder->encode(new Byte(-128)));
    }

    public function testEncodesByteZero(): void
    {
        $encoder = new Encoder();

        self::assertSame("\x51\x00", $encoder->encode(new Byte(0)));
    }

    public function testEncodesByteMaximum(): void
    {
        $encoder = new Encoder();

        self::assertSame("\x51\x7f", $encoder->encode(new Byte(127)));
    }

    public function testEncodesUShortZero(): void
    {
        $encoder = new Encoder();

        self::assertSame("\x60\x00\x00", $encoder->encode(new UShort(0)));
    }

    public function testEncodesUShortMaximum(): void
    {
        $encoder = new Encoder();

        self::assertSame("\x60\xff\xff", $encoder->encode(new UShort(65535)));
    }

    public function testEncodesShortMinimum(): void
    {
        $encoder = new Encoder();

        self::assertSame("\x61\x80\x00", $encoder->encode(new Short(-32768)));
    }

    public function testEncodesShortZero(): void
    {
        $encoder = new Encoder();

        self::assertSame("\x61\x00\x00", $encoder->encode(new Short(0)));
    }

    public function testEncodesShortMaximum(): void
    {
        $encoder = new Encoder();

        self::assertSame("\x61\x7f\xff", $encoder->encode(new Short(32767)));
    }

    public function testEncodesUIntZero(): void
    {
        $encoder = new Encoder();

        self::assertSame("\x70\x00\x00\x00\x00", $encoder->encode(new UInt(0)));
    }

    public function testEncodesUIntMaximum(): void
    {
        $encoder = new Encoder();

        self::assertSame("\x70\xff\xff\xff\xff", $encoder->encode(new UInt(4294967295)));
    }

    public function testEncodesIntMinimum(): void
    {
        $encoder = new Encoder();

        self::assertSame("\x71\x80\x00\x00\x00", $encoder->encode(new Int_(-2147483648)));
    }

    public function testEncodesIntZero(): void
    {
        $encoder = new Encoder();

        self::assertSame("\x71\x00\x00\x00\x00", $encoder->encode(new Int_(0)));
    }

    public function testEncodesIntMaximum(): void
    {
        $encoder = new Encoder();

        self::assertSame("\x71\x7f\xff\xff\xff", $encoder->encode(new Int_(2147483647)));
    }

    public function testEncodesLongMinimum(): void
    {
        $encoder = new Encoder();

        self::assertSame("\x81\x80\x00\x00\x00\x00\x00\x00\x00", $encoder->encode(new Long_(PHP_INT_MIN)));
    }

    public function testEncodesLongZero(): void
    {
        $encoder = new Encoder();

        self::assertSame("\x81\x00\x00\x00\x00\x00\x00\x00\x00", $encoder->encode(new Long_(0)));
    }

    public function testEncodesLongMaximum(): void
    {
        $encoder = new Encoder();

        self::assertSame("\x81\x7f\xff\xff\xff\xff\xff\xff\xff", $encoder->encode(new Long_(PHP_INT_MAX)));
    }

    public function testEncodesULongZero(): void
    {
        $encoder = new Encoder();

        self::assertSame("\x80\x00\x00\x00\x00\x00\x00\x00\x00", $encoder->encode(new ULong(0)));
    }

    public function testEncodesULongMaximum(): void
    {
        $encoder = new Encoder();

        self::assertSame("\x80\xff\xff\xff\xff\xff\xff\xff\xff", $encoder->encode(new ULong('18446744073709551615')));
    }

    public function testEncodesFloat32(): void
    {
        $encoder = new Encoder();

        self::assertSame("\x72\x3f\xc0\x00\x00", $encoder->encode(new Float32(1.5)));
    }

    public function testEncodesFloat64(): void
    {
        $encoder = new Encoder();

        self::assertSame("\x82\x3f\xf8\x00\x00\x00\x00\x00\x00", $encoder->encode(new Float64(1.5)));
    }

    public function testEncodesTimestamp(): void
    {
        $encoder = new Encoder();

        self::assertSame("\x83\x00\x00\x00\x00\x00\x00\x00\x01", $encoder->encode(new Timestamp(1)));
    }

    public function testEncodesUuid(): void
    {
        $encoder = new Encoder();

        self::assertSame(
            "\x98\x00\x11\x22\x33\x44\x55\x66\x77\x88\x99\xaa\xbb\xcc\xdd\xee\xff",
            $encoder->encode(new Uuid('00112233-4455-6677-8899-aabbccddeeff')),
        );
    }

    public function testEncodesDecimal32(): void
    {
        $encoder = new Encoder();

        self::assertSame("\x74\x01\x02\x03\x04", $encoder->encode(new Decimal32("\x01\x02\x03\x04")));
    }

    public function testEncodesDecimal64(): void
    {
        $encoder = new Encoder();

        self::assertSame("\x84\x01\x02\x03\x04\x05\x06\x07\x08", $encoder->encode(new Decimal64("\x01\x02\x03\x04\x05\x06\x07\x08")));
    }

    public function testEncodesDecimal128(): void
    {
        $encoder = new Encoder();
        $bytes = "\x00\x11\x22\x33\x44\x55\x66\x77\x88\x99\xaa\xbb\xcc\xdd\xee\xff";

        self::assertSame("\x94" . $bytes, $encoder->encode(new Decimal128($bytes)));
    }

    public function testEncodesChar(): void
    {
        $encoder = new Encoder();

        self::assertSame("\x73\x00\x01\xf6\x00", $encoder->encode(new Char(0x1f600)));
    }

    public function testEncodesBinary8(): void
    {
        $encoder = new Encoder();

        self::assertSame("\xa0\x06orders", $encoder->encode(new Binary('orders')));
    }

    public function testEncodesBinary32(): void
    {
        $encoder = new Encoder();
        $bytes = str_repeat('x', 256);

        self::assertSame("\xb0\x00\x00\x01\x00" . $bytes, $encoder->encode(new Binary($bytes)));
    }

    public function testEncodesString8(): void
    {
        $encoder = new Encoder();

        self::assertSame("\xa1\x06orders", $encoder->encode('orders'));
    }

    public function testEncodesString32(): void
    {
        $encoder = new Encoder();
        $text = str_repeat('x', 256);

        self::assertSame("\xb1\x00\x00\x01\x00" . $text, $encoder->encode($text));
    }

    public function testEncodesSymbol8(): void
    {
        $encoder = new Encoder();

        self::assertSame("\xa3\x06orders", $encoder->encode(new Symbol('orders')));
    }

    public function testEncodesSymbol32(): void
    {
        $encoder = new Encoder();
        $text = str_repeat('x', 256);

        self::assertSame("\xb3\x00\x00\x01\x00" . $text, $encoder->encode(new Symbol($text)));
    }

    public function testEncodesEmptyListAsList0(): void
    {
        $encoder = new Encoder();

        self::assertSame("\x45", $encoder->encode(new AmqpList([])));
    }

    public function testEncodesList8(): void
    {
        $encoder = new Encoder();

        self::assertSame(
            "\xc0\x0f\x03\xa1\x06orders\x70\x00\x00\x00\x2a\x41",
            $encoder->encode(new AmqpList(['orders', new UInt(42), true])),
        );
    }

    public function testEncodesNestedList8(): void
    {
        $encoder = new Encoder();

        self::assertSame(
            "\xc0\x07\x01\xc0\x04\x01\xa1\x01x",
            $encoder->encode(new AmqpList([new AmqpList(['x'])])),
        );
    }

    public function testEncodesList32(): void
    {
        $encoder = new Encoder();
        $text = str_repeat('x', 256);

        self::assertSame(
            "\xd0\x00\x00\x01\x09\x00\x00\x00\x01\xb1\x00\x00\x01\x00" . $text,
            $encoder->encode(new AmqpList([$text])),
        );
    }

    public function testEncodesEmptyMapAsMap8(): void
    {
        $encoder = new Encoder();

        self::assertSame("\xc1\x01\x00", $encoder->encode(new AmqpMap([])));
    }

    public function testEncodesMap8(): void
    {
        $encoder = new Encoder();

        self::assertSame(
            "\xc1\x1f\x04\xa1\x05queue\xa1\x06orders\xa3\x08priority\x70\x00\x00\x00\x05",
            $encoder->encode(new AmqpMap([
                ['queue', 'orders'],
                [new Symbol('priority'), new UInt(5)],
            ])),
        );
    }

    public function testEncodesNestedMap8(): void
    {
        $encoder = new Encoder();

        self::assertSame(
            "\xc1\x10\x02\xa1\x06nested\xc1\x05\x02\xa1\x01x\x41",
            $encoder->encode(new AmqpMap([
                ['nested', new AmqpMap([
                    ['x', true],
                ])],
            ])),
        );
    }

    public function testEncodesMap32(): void
    {
        $encoder = new Encoder();
        $text = str_repeat('x', 256);

        self::assertSame(
            "\xd1\x00\x00\x01\x12\x00\x00\x00\x02\xa1\x07payload\xb1\x00\x00\x01\x00" . $text,
            $encoder->encode(new AmqpMap([
                ['payload', $text],
            ])),
        );
    }

    public function testEncodesEmptyArray8WithElementConstructor(): void
    {
        $encoder = new Encoder();

        self::assertSame("\xe0\x02\x00\xa3", $encoder->encode(new AmqpArray("\xa3", [])));
    }

    public function testEncodesSymbolArray8(): void
    {
        $encoder = new Encoder();

        self::assertSame(
            "\xe0\x07\x02\xa3\x01a\x02bb",
            $encoder->encode(new AmqpArray("\xa3", [new Symbol('a'), new Symbol('bb')])),
        );
    }

    public function testEncodesUIntArray8(): void
    {
        $encoder = new Encoder();

        self::assertSame(
            "\xe0\x0a\x02\x70\x00\x00\x00\x01\x00\x00\x00\x02",
            $encoder->encode(new AmqpArray("\x70", [new UInt(1), new UInt(2)])),
        );
    }

    public function testEncodesNestedArray8(): void
    {
        $encoder = new Encoder();

        self::assertSame(
            "\xe0\x07\x01\xe0\x04\x01\xa3\x01x",
            $encoder->encode(new AmqpArray("\xe0", [
                new AmqpArray("\xa3", [new Symbol('x')]),
            ])),
        );
    }

    public function testEncodesArray32(): void
    {
        $encoder = new Encoder();
        $text = str_repeat('x', 256);

        self::assertSame(
            "\xf0\x00\x00\x01\x09\x00\x00\x00\x01\xb1\x00\x00\x01\x00" . $text,
            $encoder->encode(new AmqpArray("\xb1", [$text])),
        );
    }

    public function testRejectsArrayItemWithMismatchedElementConstructor(): void
    {
        $encoder = new Encoder();

        $this->expectException(EncodeException::class);
        $this->expectExceptionMessage('AMQP array item does not match the declared element constructor.');

        $encoder->encode(new AmqpArray("\xa3", ['string']));
    }

    public function testRejectsUnsupportedValue(): void
    {
        $encoder = new Encoder();

        $this->expectException(EncodeException::class);
        $this->expectExceptionMessage('Cannot encode PHP value of type int as AMQP value.');

        $encoder->encode(1);
    }
}
