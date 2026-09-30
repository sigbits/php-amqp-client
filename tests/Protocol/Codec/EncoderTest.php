<?php

declare(strict_types=1);

namespace Sigbits\Amqp\Tests\Protocol\Codec;

use PHPUnit\Framework\TestCase;
use Sigbits\Amqp\Protocol\Codec\EncodeException;
use Sigbits\Amqp\Protocol\Codec\Encoder;
use Sigbits\Amqp\Protocol\Type\Int_;
use Sigbits\Amqp\Protocol\Type\UByte;
use Sigbits\Amqp\Protocol\Type\UInt;
use Sigbits\Amqp\Protocol\Type\UShort;

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

    public function testRejectsUnsupportedValue(): void
    {
        $encoder = new Encoder();

        $this->expectException(EncodeException::class);
        $this->expectExceptionMessage('Cannot encode PHP value of type int as AMQP value.');

        $encoder->encode(1);
    }
}
