<?php

declare(strict_types=1);

namespace Sigbits\Amqp\Tests\Protocol\Performative;

use PHPUnit\Framework\TestCase;
use Sigbits\Amqp\Protocol\Performative\Open;
use Sigbits\Amqp\Protocol\Performative\OpenCodec;
use Sigbits\Amqp\Protocol\Performative\PerformativeException;

final class OpenCodecTest extends TestCase
{
    public function testEncodesMinimalOpenPerformative(): void
    {
        $codec = new OpenCodec();

        self::assertSame(
            "\x00\x53\x10\xc0\x09\x01\xa1\x06client",
            $codec->encode(new Open(containerId: 'client')),
        );
    }

    public function testDecodesMinimalOpenPerformative(): void
    {
        $codec = new OpenCodec();

        self::assertEquals(
            new Open(containerId: 'client'),
            $codec->decode("\x00\x53\x10\xc0\x09\x01\xa1\x06client"),
        );
    }

    public function testDecodesList32OpenPerformative(): void
    {
        $codec = new OpenCodec();

        self::assertEquals(
            new Open(containerId: 'server'),
            $codec->decode("\x00\x53\x10\xd0\x00\x00\x00\x0c\x00\x00\x00\x01\xa1\x06server"),
        );
    }

    public function testRejectsWrongDescriptor(): void
    {
        $codec = new OpenCodec();

        $this->expectException(PerformativeException::class);
        $this->expectExceptionMessage('Expected AMQP open performative descriptor.');

        $codec->decode("\x00\x53\x11\xc0\x09\x01\xa1\x06client");
    }

    public function testRejectsOpenWithoutContainerId(): void
    {
        $codec = new OpenCodec();

        $this->expectException(PerformativeException::class);
        $this->expectExceptionMessage('AMQP open performative requires container-id.');

        $codec->decode("\x00\x53\x10\x45");
    }

    public function testRejectsTruncatedOpenContainerId(): void
    {
        $codec = new OpenCodec();

        $this->expectException(PerformativeException::class);
        $this->expectExceptionMessage('Truncated AMQP open performative.');

        $codec->decode("\x00\x53\x10\xc0\x09\x01\xa1\x06cli");
    }
}
