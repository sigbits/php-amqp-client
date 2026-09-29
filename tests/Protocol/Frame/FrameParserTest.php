<?php

declare(strict_types=1);

namespace Sigbits\Amqp\Tests\Protocol\Frame;

use PHPUnit\Framework\TestCase;
use Sigbits\Amqp\Protocol\Frame\Frame;
use Sigbits\Amqp\Protocol\Frame\FrameException;
use Sigbits\Amqp\Protocol\Frame\FrameHeader;
use Sigbits\Amqp\Protocol\Frame\FrameParser;

final class FrameParserTest extends TestCase
{
    public function testParsesCompleteFrame(): void
    {
        $parser = new FrameParser();

        self::assertEquals(
            [
                new Frame(
                    new FrameHeader(size: 11, dataOffset: 2, type: 0, channel: 1),
                    'abc',
                ),
            ],
            $parser->push("\x00\x00\x00\x0b\x02\x00\x00\x01abc"),
        );
    }

    public function testBuffersPartialFrameUntilComplete(): void
    {
        $parser = new FrameParser();

        self::assertSame([], $parser->push("\x00\x00\x00"));
        self::assertSame([], $parser->push("\x0b\x02\x00\x00\x01a"));

        self::assertEquals(
            [
                new Frame(
                    new FrameHeader(size: 11, dataOffset: 2, type: 0, channel: 1),
                    'abc',
                ),
            ],
            $parser->push('bc'),
        );
    }

    public function testParsesMultipleFramesFromOneChunk(): void
    {
        $parser = new FrameParser();

        self::assertEquals(
            [
                new Frame(new FrameHeader(size: 8, dataOffset: 2, type: 0, channel: 0), ''),
                new Frame(new FrameHeader(size: 9, dataOffset: 2, type: 0, channel: 0), 'x'),
            ],
            $parser->push("\x00\x00\x00\x08\x02\x00\x00\x00\x00\x00\x00\x09\x02\x00\x00\x00x"),
        );
    }

    public function testDefaultMaximumAcceptsLargerBrokerFrames(): void
    {
        $parser = new FrameParser();
        $payload = str_repeat('x', 1024);

        self::assertEquals(
            [
                new Frame(
                    new FrameHeader(size: FrameHeader::LENGTH + strlen($payload), dataOffset: 2, type: 0, channel: 1),
                    $payload,
                ),
            ],
            $parser->push(pack('N', FrameHeader::LENGTH + strlen($payload)) . "\x02\x00\x00\x01" . $payload),
        );
    }

    public function testRejectsFrameExceedingConfiguredMaximum(): void
    {
        $parser = new FrameParser(maxFrameSize: 8);

        $this->expectException(FrameException::class);
        $this->expectExceptionMessage('AMQP frame size exceeds configured maximum.');

        $parser->push("\x00\x00\x00\x09\x02\x00\x00\x00x");
    }
}
