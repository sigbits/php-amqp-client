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

    public function testRejectsUnsupportedFormatCode(): void
    {
        $decoder = new Decoder();

        $this->expectException(DecodeException::class);
        $this->expectExceptionMessage('Unsupported AMQP format code 0x43.');

        $decoder->decode("\x43");
    }
}
