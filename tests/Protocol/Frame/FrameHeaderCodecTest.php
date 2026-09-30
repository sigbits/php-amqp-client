<?php

declare(strict_types=1);

namespace Sigbits\Amqp\Tests\Protocol\Frame;

use PHPUnit\Framework\TestCase;
use Sigbits\Amqp\Protocol\Frame\FrameException;
use Sigbits\Amqp\Protocol\Frame\FrameHeader;
use Sigbits\Amqp\Protocol\Frame\FrameHeaderCodec;

final class FrameHeaderCodecTest extends TestCase
{
    public function testEncodesFrameHeader(): void
    {
        $codec = new FrameHeaderCodec();

        self::assertSame(
            "\x00\x00\x00\x08\x02\x00\x00\x00",
            $codec->encode(new FrameHeader(size: 8, dataOffset: 2, type: 0, channel: 0)),
        );
    }

    public function testDecodesFrameHeader(): void
    {
        $codec = new FrameHeaderCodec();

        self::assertEquals(
            new FrameHeader(size: 512, dataOffset: 2, type: 0, channel: 65535),
            $codec->decode("\x00\x00\x02\x00\x02\x00\xff\xff"),
        );
    }

    public function testRejectsTruncatedFrameHeader(): void
    {
        $codec = new FrameHeaderCodec();

        $this->expectException(FrameException::class);
        $this->expectExceptionMessage('Truncated AMQP frame header.');

        $codec->decode("\x00\x00\x00\x08");
    }

    public function testRejectsFrameSizeSmallerThanHeader(): void
    {
        $this->expectException(FrameException::class);
        $this->expectExceptionMessage('AMQP frame size must be at least 8 bytes.');

        new FrameHeader(size: 7, dataOffset: 2, type: 0, channel: 0);
    }

    public function testRejectsDataOffsetSmallerThanHeader(): void
    {
        $this->expectException(FrameException::class);
        $this->expectExceptionMessage('AMQP frame data offset must be at least 2.');

        new FrameHeader(size: 8, dataOffset: 1, type: 0, channel: 0);
    }

    public function testRejectsDataOffsetBeyondFrameSize(): void
    {
        $this->expectException(FrameException::class);
        $this->expectExceptionMessage('AMQP frame data offset exceeds frame size.');

        new FrameHeader(size: 8, dataOffset: 3, type: 0, channel: 0);
    }

    public function testRejectsEncodedDataOffsetBeyondFrameSize(): void
    {
        $codec = new FrameHeaderCodec();

        $this->expectException(FrameException::class);
        $this->expectExceptionMessage('AMQP frame data offset exceeds frame size.');

        $codec->decode("\x00\x00\x00\x08\x03\x00\x00\x00");
    }
}
