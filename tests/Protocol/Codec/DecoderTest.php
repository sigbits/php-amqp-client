<?php

declare(strict_types=1);

namespace Sigbits\Amqp\Tests\Protocol\Codec;

use PHPUnit\Framework\TestCase;
use Sigbits\Amqp\Protocol\Codec\DecodeException;
use Sigbits\Amqp\Protocol\Codec\Decoder;

final class DecoderTest extends TestCase
{
    public function testDecodesNullEncodingAsNull(): void
    {
        $decoder = new Decoder();

        self::assertNull($decoder->decode("\x40"));
    }

    public function testRejectsUnsupportedFormatCode(): void
    {
        $decoder = new Decoder();

        $this->expectException(DecodeException::class);
        $this->expectExceptionMessage('Unsupported AMQP format code 0x41.');

        $decoder->decode("\x41");
    }
}
