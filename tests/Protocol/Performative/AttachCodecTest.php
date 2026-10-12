<?php

declare(strict_types=1);

namespace Sigbits\Amqp\Tests\Protocol\Performative;

use PHPUnit\Framework\TestCase;
use Sigbits\Amqp\Protocol\Performative\Attach;
use Sigbits\Amqp\Protocol\Performative\AttachCodec;
use Sigbits\Amqp\Protocol\Performative\LinkRole;
use Sigbits\Amqp\Protocol\Performative\PerformativeException;
use Sigbits\Amqp\Protocol\Performative\Source;
use Sigbits\Amqp\Protocol\Performative\Target;

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

    public function testDecodesBrokerAttachWithUint0HandleAndExtraFields(): void
    {
        $codec = new AttachCodec();

        self::assertEquals(
            new Attach(
                name: 'sender',
                handle: 0,
                role: LinkRole::Receiver,
                targetAddress: 'orders.test',
            ),
            $codec->decode(
                "\x00\x53\x12\xc0\x23\x07\xa1\x06sender"
                . "\x43"
                . "\x41"
                . "\x50\x02"
                . "\x50\x00"
                . "\x40"
                . "\x00\x53\x29\xc0\x0e\x01\xa1\x0borders.test",
            ),
        );
    }

    public function testEncodesAttachWithSourceAndTargetTermini(): void
    {
        $codec = new AttachCodec();

        self::assertSame(
            "\x00\x53\x12\xc0\x2b\x07\xa1\x04link"
            . "\x70\x00\x00\x00\x00"
            . "\x41"
            . "\x40\x40"
            . "\x00\x53\x28\xc0\x09\x01\xa1\x06source"
            . "\x00\x53\x29\xc0\x09\x01\xa1\x06target",
            $codec->encode(new Attach(
                name: 'link',
                handle: 0,
                role: LinkRole::Receiver,
                source: new Source(address: 'source'),
                target: new Target(address: 'target'),
            )),
        );
    }

    public function testEncodesAttachWithSourceAndTargetLifecycleFields(): void
    {
        $codec = new AttachCodec();

        self::assertSame(
            "\x00\x53\x12\xc0\x4f\x07\xa1\x04link"
            . "\x70\x00\x00\x00\x00"
            . "\x41"
            . "\x40\x40"
            . "\x00\x53\x28\xc0\x1b\x05\xa1\x06source\x70\x00\x00\x00\x01\xa3\x05never\x70\x00\x00\x00\x3c\x41"
            . "\x00\x53\x29\xc0\x1b\x05\xa1\x06target\x70\x00\x00\x00\x02\xa3\x05never\x70\x00\x00\x00\x78\x42",
            $codec->encode(new Attach(
                name: 'link',
                handle: 0,
                role: LinkRole::Receiver,
                source: new Source(
                    address: 'source',
                    durable: 1,
                    expiryPolicy: 'never',
                    timeout: 60,
                    dynamic: true,
                ),
                target: new Target(
                    address: 'target',
                    durable: 2,
                    expiryPolicy: 'never',
                    timeout: 120,
                    dynamic: false,
                ),
            )),
        );
    }

    public function testDecodesAttachWithSourceAndTargetTermini(): void
    {
        $codec = new AttachCodec();

        self::assertEquals(
            new Attach(
                name: 'link',
                handle: 0,
                role: LinkRole::Receiver,
                source: new Source(address: 'source'),
                target: new Target(address: 'target'),
            ),
            $codec->decode(
                "\x00\x53\x12\xc0\x27\x07\xa1\x04link"
                . "\x43"
                . "\x41"
                . "\x40\x40"
                . "\x00\x53\x28\xc0\x09\x01\xa1\x06source"
                . "\x00\x53\x29\xc0\x09\x01\xa1\x06target",
            ),
        );
    }

    public function testDecodesAttachWithSourceAndTargetLifecycleFields(): void
    {
        $codec = new AttachCodec();

        self::assertEquals(
            new Attach(
                name: 'link',
                handle: 0,
                role: LinkRole::Receiver,
                source: new Source(
                    address: 'source',
                    durable: 1,
                    expiryPolicy: 'never',
                    timeout: 60,
                    dynamic: true,
                ),
                target: new Target(
                    address: 'target',
                    durable: 2,
                    expiryPolicy: 'never',
                    timeout: 120,
                    dynamic: false,
                ),
            ),
            $codec->decode(
                "\x00\x53\x12\xc0\x4b\x07\xa1\x04link"
                . "\x43"
                . "\x41"
                . "\x40\x40"
                . "\x00\x53\x28\xc0\x1b\x05\xa1\x06source\x70\x00\x00\x00\x01\xa3\x05never\x70\x00\x00\x00\x3c\x41"
                . "\x00\x53\x29\xc0\x1b\x05\xa1\x06target\x70\x00\x00\x00\x02\xa3\x05never\x70\x00\x00\x00\x78\x42",
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

    public function testEncodesSenderAttachWithTargetAddress(): void
    {
        $codec = new AttachCodec();

        self::assertSame(
            "\x00\x53\x12\xc0\x25\x07\xa1\x06sender"
            . "\x70\x00\x00\x00\x00"
            . "\x42"
            . "\x40\x40\x40"
            . "\x00\x53\x29\xc0\x0e\x01\xa1\x0borders.test",
            $codec->encode(new Attach(
                name: 'sender',
                handle: 0,
                role: LinkRole::Sender,
                targetAddress: 'orders.test',
            )),
        );
    }

    public function testEncodesSenderAttachWithInitialDeliveryCount(): void
    {
        $codec = new AttachCodec();

        self::assertSame(
            "\x00\x53\x12\xc0\x28\x0a\xa1\x06sender"
            . "\x70\x00\x00\x00\x00"
            . "\x42"
            . "\x40\x40\x40"
            . "\x00\x53\x29\xc0\x0e\x01\xa1\x0borders.test"
            . "\x40\x40\x43",
            $codec->encode(new Attach(
                name: 'sender',
                handle: 0,
                role: LinkRole::Sender,
                targetAddress: 'orders.test',
                initialDeliveryCount: 0,
            )),
        );
    }

    public function testEncodesReceiverAttachWithSourceAddress(): void
    {
        $codec = new AttachCodec();

        self::assertSame(
            "\x00\x53\x12\xc0\x27\x07\xa1\x08receiver"
            . "\x70\x00\x00\x00\x01"
            . "\x41"
            . "\x40\x40"
            . "\x00\x53\x28\xc0\x0e\x01\xa1\x0borders.test"
            . "\x40",
            $codec->encode(new Attach(
                name: 'receiver',
                handle: 1,
                role: LinkRole::Receiver,
                sourceAddress: 'orders.test',
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
