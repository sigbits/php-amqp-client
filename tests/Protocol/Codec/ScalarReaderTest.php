<?php

declare(strict_types=1);

namespace Sigbits\Amqp\Tests\Protocol\Codec;

use PHPUnit\Framework\TestCase;
use Sigbits\Amqp\Protocol\Codec\DecodeException;
use Sigbits\Amqp\Protocol\Codec\ScalarReader;

final class ScalarReaderTest extends TestCase
{
    public function testReadsUIntZeroCompactEncodingAtOffset(): void
    {
        $reader = new ScalarReader();

        self::assertSame([0, 2], $reader->readNullableUInt("\xff\x43", 1, 2));
    }

    public function testReadsSmallUIntAtOffset(): void
    {
        $reader = new ScalarReader();

        self::assertSame([255, 3], $reader->readNullableUInt("\xff\x52\xff", 1, 3));
    }

    public function testReadsUIntAtOffset(): void
    {
        $reader = new ScalarReader();

        self::assertSame([1000, 6], $reader->readNullableUInt("\xff\x70\x00\x00\x03\xe8", 1, 6));
    }

    public function testReadsNullUIntAtOffset(): void
    {
        $reader = new ScalarReader();

        self::assertSame([null, 2], $reader->readNullableUInt("\xff\x40", 1, 2));
    }

    public function testSkipsUIntAtOffset(): void
    {
        $reader = new ScalarReader();

        self::assertSame(6, $reader->skipValue("\xff\x70\x00\x00\x00\x01", 1, 6));
    }

    public function testRejectsSkippingPastEnd(): void
    {
        $reader = new ScalarReader();

        $this->expectException(DecodeException::class);
        $this->expectExceptionMessage('Truncated AMQP value.');

        $reader->skipValue('', 0, 0);
    }

    public function testRejectsTruncatedSmallUInt(): void
    {
        $reader = new ScalarReader();

        $this->expectException(DecodeException::class);
        $this->expectExceptionMessage('Truncated AMQP uint value.');

        $reader->readNullableUInt("\x52", 0, 1);
    }

    public function testRejectsUnsupportedUIntEncoding(): void
    {
        $reader = new ScalarReader();

        $this->expectException(DecodeException::class);
        $this->expectExceptionMessage('Unsupported AMQP format code 0x44.');

        $reader->readNullableUInt("\x44", 0, 1);
    }
}
