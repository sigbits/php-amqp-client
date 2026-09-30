<?php

declare(strict_types=1);

namespace Sigbits\Amqp\Tests\Protocol\Performative;

use PHPUnit\Framework\TestCase;
use Sigbits\Amqp\Protocol\Performative\Close;
use Sigbits\Amqp\Protocol\Performative\CloseCodec;
use Sigbits\Amqp\Protocol\Performative\PerformativeException;

final class CloseCodecTest extends TestCase
{
    public function testEncodesEmptyClosePerformative(): void
    {
        $codec = new CloseCodec();

        self::assertSame("\x00\x53\x18\x45", $codec->encode(new Close()));
    }

    public function testDecodesEmptyClosePerformative(): void
    {
        $codec = new CloseCodec();

        self::assertEquals(new Close(), $codec->decode("\x00\x53\x18\x45"));
    }

    public function testRejectsWrongDescriptor(): void
    {
        $codec = new CloseCodec();

        $this->expectException(PerformativeException::class);
        $this->expectExceptionMessage('Expected AMQP close performative descriptor.');

        $codec->decode("\x00\x53\x10\x45");
    }

    public function testRejectsTruncatedClose(): void
    {
        $codec = new CloseCodec();

        $this->expectException(PerformativeException::class);
        $this->expectExceptionMessage('Truncated AMQP close performative.');

        $codec->decode("\x00\x53\x18");
    }

    public function testDecodesCloseWithNullErrorField(): void
    {
        $codec = new CloseCodec();

        self::assertEquals(new Close(), $codec->decode("\x00\x53\x18\xc0\x02\x01\x40"));
    }

    public function testDecodesCloseWithErrorPayload(): void
    {
        $codec = new CloseCodec();

        self::assertEquals(
            new Close(),
            $codec->decode("\x00\x53\x18\xc0\x16\x01\x00\x53\x1d\xc0\x10\x02\xa3\x08amqp:not\xa1\x03bye"),
        );
    }
}
