<?php

declare(strict_types=1);

namespace Sigbits\Amqp\Tests\Protocol\Performative;

use PHPUnit\Framework\TestCase;
use Sigbits\Amqp\Protocol\Performative\Begin;
use Sigbits\Amqp\Protocol\Performative\BeginCodec;
use Sigbits\Amqp\Protocol\Performative\PerformativeException;

final class BeginCodecTest extends TestCase
{
    public function testEncodesMinimalBeginPerformative(): void
    {
        $codec = new BeginCodec();

        self::assertSame(
            "\x00\x53\x11\xc0\x11\x04\x40"
            . "\x70\x00\x00\x00\x00"
            . "\x70\x7f\xff\xff\xff"
            . "\x70\x7f\xff\xff\xff",
            $codec->encode(new Begin(
                remoteChannel: null,
                nextOutgoingId: 0,
                incomingWindow: 2_147_483_647,
                outgoingWindow: 2_147_483_647,
            )),
        );
    }

    public function testDecodesMinimalBeginPerformative(): void
    {
        $codec = new BeginCodec();

        self::assertEquals(
            new Begin(
                remoteChannel: null,
                nextOutgoingId: 0,
                incomingWindow: 2_147_483_647,
                outgoingWindow: 2_147_483_647,
            ),
            $codec->decode(
                "\x00\x53\x11\xc0\x11\x04\x40"
                . "\x70\x00\x00\x00\x00"
                . "\x70\x7f\xff\xff\xff"
                . "\x70\x7f\xff\xff\xff",
            ),
        );
    }

    public function testRejectsWrongDescriptor(): void
    {
        $codec = new BeginCodec();

        $this->expectException(PerformativeException::class);
        $this->expectExceptionMessage('Expected AMQP begin performative descriptor.');

        $codec->decode("\x00\x53\x10\x45");
    }

    public function testRejectsBeginWithoutRequiredFields(): void
    {
        $codec = new BeginCodec();

        $this->expectException(PerformativeException::class);
        $this->expectExceptionMessage('AMQP begin performative requires next-outgoing-id, incoming-window, and outgoing-window.');

        $codec->decode("\x00\x53\x11\x45");
    }

    public function testRejectsTruncatedBegin(): void
    {
        $codec = new BeginCodec();

        $this->expectException(PerformativeException::class);
        $this->expectExceptionMessage('Truncated AMQP begin performative.');

        $codec->decode(
            "\x00\x53\x11\xc0\x11\x04\x40"
            . "\x70\x00\x00\x00\x00"
            . "\x70\x7f\xff\xff\xff",
        );
    }
}
