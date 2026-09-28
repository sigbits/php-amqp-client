<?php

declare(strict_types=1);

namespace Sigbits\Amqp\Tests\Protocol\Codec;

use PHPUnit\Framework\TestCase;
use Sigbits\Amqp\Protocol\Codec\EncodeException;
use Sigbits\Amqp\Protocol\Codec\Encoder;

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

    public function testRejectsUnsupportedValue(): void
    {
        $encoder = new Encoder();

        $this->expectException(EncodeException::class);
        $this->expectExceptionMessage('Cannot encode PHP value of type int as AMQP value.');

        $encoder->encode(1);
    }
}
