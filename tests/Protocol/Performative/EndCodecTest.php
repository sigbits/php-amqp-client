<?php

declare(strict_types=1);

namespace Sigbits\Amqp\Tests\Protocol\Performative;

use PHPUnit\Framework\TestCase;
use Sigbits\Amqp\Protocol\Performative\End;
use Sigbits\Amqp\Protocol\Performative\EndCodec;
use Sigbits\Amqp\Protocol\Performative\PerformativeException;

final class EndCodecTest extends TestCase
{
    public function testEncodesEmptyEndPerformative(): void
    {
        $codec = new EndCodec();

        self::assertSame("\x00\x53\x17\x45", $codec->encode(new End()));
    }

    public function testDecodesEmptyEndPerformative(): void
    {
        $codec = new EndCodec();

        self::assertEquals(new End(), $codec->decode("\x00\x53\x17\x45"));
    }

    public function testRejectsWrongDescriptor(): void
    {
        $codec = new EndCodec();

        $this->expectException(PerformativeException::class);
        $this->expectExceptionMessage('Expected AMQP end performative descriptor.');

        $codec->decode("\x00\x53\x18\x45");
    }

    public function testRejectsTruncatedEnd(): void
    {
        $codec = new EndCodec();

        $this->expectException(PerformativeException::class);
        $this->expectExceptionMessage('Truncated AMQP end performative.');

        $codec->decode("\x00\x53\x17");
    }

    public function testRejectsEndWithErrorPayloadUntilErrorTypeIsSupported(): void
    {
        $codec = new EndCodec();

        $this->expectException(PerformativeException::class);
        $this->expectExceptionMessage('AMQP end error payload is not supported yet.');

        $codec->decode("\x00\x53\x17\xc0\x01\x01");
    }
}
