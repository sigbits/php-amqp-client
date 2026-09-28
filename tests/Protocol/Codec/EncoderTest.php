<?php

declare(strict_types=1);

namespace Sigbits\Amqp\Tests\Protocol\Codec;

use PHPUnit\Framework\TestCase;
use Sigbits\Amqp\Protocol\Codec\EncodeException;
use Sigbits\Amqp\Protocol\Codec\Encoder;
use Sigbits\Amqp\Protocol\Type\UByte;

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

    public function testRejectsUnsupportedValue(): void
    {
        $encoder = new Encoder();

        $this->expectException(EncodeException::class);
        $this->expectExceptionMessage('Cannot encode PHP value of type int as AMQP value.');

        $encoder->encode(1);
    }
}
