<?php

declare(strict_types=1);

namespace Sigbits\Amqp\Tests\Protocol\Performative;

use PHPUnit\Framework\TestCase;
use Sigbits\Amqp\Protocol\Performative\Attach;
use Sigbits\Amqp\Protocol\Performative\AttachCodec;
use Sigbits\Amqp\Protocol\Performative\LinkRole;
use Sigbits\Amqp\Protocol\Performative\PerformativeException;

final class AttachCodecTest extends TestCase
{
    public function testEncodesMinimalSenderAttachPerformative(): void
    {
        $codec = new AttachCodec();

        self::assertSame(
            "\x00\x53\x12\xc0\x0f\x03\xa1\x06sender"
            . "\x70\x00\x00\x00\x00"
            . "\x42",
            $codec->encode(new Attach(
                name: 'sender',
                handle: 0,
                role: LinkRole::Sender,
            )),
        );
    }

    public function testDecodesMinimalSenderAttachPerformative(): void
    {
        $codec = new AttachCodec();

        self::assertEquals(
            new Attach(
                name: 'sender',
                handle: 0,
                role: LinkRole::Sender,
            ),
            $codec->decode(
                "\x00\x53\x12\xc0\x0f\x03\xa1\x06sender"
                . "\x70\x00\x00\x00\x00"
                . "\x42",
            ),
        );
    }

    public function testEncodesMinimalReceiverAttachPerformative(): void
    {
        $codec = new AttachCodec();

        self::assertSame(
            "\x00\x53\x12\xc0\x11\x03\xa1\x08receiver"
            . "\x70\x00\x00\x00\x01"
            . "\x41",
            $codec->encode(new Attach(
                name: 'receiver',
                handle: 1,
                role: LinkRole::Receiver,
            )),
        );
    }

    public function testRejectsWrongDescriptor(): void
    {
        $codec = new AttachCodec();

        $this->expectException(PerformativeException::class);
        $this->expectExceptionMessage('Expected AMQP attach performative descriptor.');

        $codec->decode("\x00\x53\x13\x45");
    }

    public function testRejectsAttachWithoutRequiredFields(): void
    {
        $codec = new AttachCodec();

        $this->expectException(PerformativeException::class);
        $this->expectExceptionMessage('AMQP attach performative requires name, handle, and role.');

        $codec->decode("\x00\x53\x12\x45");
    }

    public function testRejectsTruncatedAttach(): void
    {
        $codec = new AttachCodec();

        $this->expectException(PerformativeException::class);
        $this->expectExceptionMessage('Truncated AMQP attach performative.');

        $codec->decode("\x00\x53\x12\xc0\x0f\x03\xa1\x06send");
    }
}
