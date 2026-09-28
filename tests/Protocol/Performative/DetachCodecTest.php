<?php

declare(strict_types=1);

namespace Sigbits\Amqp\Tests\Protocol\Performative;

use PHPUnit\Framework\TestCase;
use Sigbits\Amqp\Protocol\Performative\Detach;
use Sigbits\Amqp\Protocol\Performative\DetachCodec;
use Sigbits\Amqp\Protocol\Performative\PerformativeException;

final class DetachCodecTest extends TestCase
{
    public function testEncodesMinimalDetachPerformative(): void
    {
        $codec = new DetachCodec();

        self::assertSame(
            "\x00\x53\x16\xc0\x06\x01\x70\x00\x00\x00\x00",
            $codec->encode(new Detach(handle: 0)),
        );
    }

    public function testDecodesMinimalDetachPerformative(): void
    {
        $codec = new DetachCodec();

        self::assertEquals(
            new Detach(handle: 0),
            $codec->decode("\x00\x53\x16\xc0\x06\x01\x70\x00\x00\x00\x00"),
        );
    }

    public function testRejectsWrongDescriptor(): void
    {
        $codec = new DetachCodec();

        $this->expectException(PerformativeException::class);
        $this->expectExceptionMessage('Expected AMQP detach performative descriptor.');

        $codec->decode("\x00\x53\x17\x45");
    }

    public function testRejectsDetachWithoutHandle(): void
    {
        $codec = new DetachCodec();

        $this->expectException(PerformativeException::class);
        $this->expectExceptionMessage('AMQP detach performative requires handle.');

        $codec->decode("\x00\x53\x16\x45");
    }

    public function testRejectsTruncatedDetach(): void
    {
        $codec = new DetachCodec();

        $this->expectException(PerformativeException::class);
        $this->expectExceptionMessage('Truncated AMQP detach performative.');

        $codec->decode("\x00\x53\x16\xc0\x06\x01\x70\x00");
    }
}
