<?php

declare(strict_types=1);

namespace Sigbits\Amqp\Tests\Protocol\Performative;

use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use Sigbits\Amqp\Protocol\Performative\Attach;
use Sigbits\Amqp\Protocol\Performative\AttachCodec;
use Sigbits\Amqp\Protocol\Performative\LinkRole;
use Sigbits\Amqp\Protocol\Performative\Modified;
use Sigbits\Amqp\Protocol\Performative\PerformativeException;
use Sigbits\Amqp\Protocol\Performative\Released;
use Sigbits\Amqp\Protocol\Performative\Source;
use Sigbits\Amqp\Protocol\Performative\Target;
use Sigbits\Amqp\Protocol\Type\AmqpMap;
use Sigbits\Amqp\Protocol\Type\Symbol;
use Sigbits\Amqp\Protocol\Type\UInt;
use Sigbits\Amqp\Protocol\Type\ULong;

final class AttachCodecTest extends TestCase
{
    public function testEncodesMinimalSenderAttachPerformative(): void
    {
        $codec = new AttachCodec();

        self::assertSame(
            "\x00\x53\x12\xc0\x16\x0a\xa1\x06sender"
            . "\x70\x00\x00\x00\x00"
            . "\x42"
            . "\x40\x40\x40\x40\x40\x40"
            . "\x43",
            $codec->encode(new Attach(
                name: 'sender',
                handle: 0,
                role: LinkRole::Sender,
                initialDeliveryCount: 0,
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
                initialDeliveryCount: 0,
            ),
            $codec->decode(
                "\x00\x53\x12\xc0\x16\x0a\xa1\x06sender"
                . "\x70\x00\x00\x00\x00"
                . "\x42"
                . "\x40\x40\x40\x40\x40\x40"
                . "\x43",
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
                senderSettleMode: 2,
                receiverSettleMode: 0,
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

    public function testDecodesAttachSettlementModes(): void
    {
        $codec = new AttachCodec();

        $attach = $codec->decode(
            "\x00\x53\x12\xc0\x23\x07\xa1\x06sender"
            . "\x43"
            . "\x41"
            . "\x50\x02"
            . "\x50\x00"
            . "\x40"
            . "\x00\x53\x29\xc0\x0e\x01\xa1\x0borders.test",
        );

        self::assertSame(2, $attach->senderSettleMode);
        self::assertSame(0, $attach->receiverSettleMode);
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

    public function testEncodesAttachWithSourceAndTargetDynamicPropertiesAndCapabilities(): void
    {
        $codec = new AttachCodec();

        self::assertSame(
            "\x00\x53\x12\xc0\x81\x07\xa1\x04link"
            . "\x70\x00\x00\x00\x00"
            . "\x41"
            . "\x40\x40"
            . "\x00\x53\x28\xc0\x3d\x0b\xa1\x06source\x40\x40\x40\x41"
            . "\xc1\x15\x02\xa3\x0dx-opt-created\x70\x65\x53\xf1\x00"
            . "\x40\x40\x40\x40"
            . "\xe0\x13\x01\xa3\x10temporary-source"
            . "\x00\x53\x29\xc0\x2b\x07\xa1\x06target\x40\x40\x40\x41"
            . "\xc1\x07\x02\xa3\x03foo\x41"
            . "\xe0\x13\x01\xa3\x10temporary-target",
            $codec->encode(new Attach(
                name: 'link',
                handle: 0,
                role: LinkRole::Receiver,
                source: new Source(
                    address: 'source',
                    dynamic: true,
                    dynamicNodeProperties: new AmqpMap([
                        [new Symbol('x-opt-created'), new UInt(1700000000)],
                    ]),
                    capabilities: ['temporary-source'],
                ),
                target: new Target(
                    address: 'target',
                    dynamic: true,
                    dynamicNodeProperties: new AmqpMap([
                        [new Symbol('foo'), true],
                    ]),
                    capabilities: ['temporary-target'],
                ),
            )),
        );
    }

    public function testEncodesAttachWithSourceDistributionModeAndOutcomes(): void
    {
        $codec = new AttachCodec();

        self::assertSame(
            "\x00\x53\x12\xc0\x41\x07\xa1\x04link"
            . "\x70\x00\x00\x00\x00"
            . "\x41"
            . "\x40\x40"
            . "\x00\x53\x28\xc0\x2c\x0a\xa1\x06source\x40\x40\x40\x40\x40\xa3\x04move\x40\x40"
            . "\xe0\x14\x02\xa3\x08accepted\x08rejected"
            . "\x40",
            $codec->encode(new Attach(
                name: 'link',
                handle: 0,
                role: LinkRole::Receiver,
                source: new Source(
                    address: 'source',
                    distributionMode: 'move',
                    outcomes: ['accepted', 'rejected'],
                ),
            )),
        );
    }

    public function testEncodesAttachWithSourceFilter(): void
    {
        $codec = new AttachCodec();

        self::assertSame(
            "\x00\x53\x12\xc0\x40\x07\xa1\x04link"
            . "\x70\x00\x00\x00\x00"
            . "\x41"
            . "\x40\x40"
            . "\x00\x53\x28\xc0\x2b\x08\xa1\x06source\x40\x40\x40\x40\x40\x40"
            . "\xc1\x1a\x02\xa3\x08selector\xa1\x0dcolor = 'red'"
            . "\x40",
            $codec->encode(new Attach(
                name: 'link',
                handle: 0,
                role: LinkRole::Receiver,
                source: new Source(
                    address: 'source',
                    filter: new AmqpMap([
                        [new Symbol('selector'), "color = 'red'"],
                    ]),
                ),
            )),
        );
    }

    public function testEncodesAttachWithSourceDefaultOutcome(): void
    {
        $codec = new AttachCodec();

        self::assertSame(
            "\x00\x53\x12\xc0\x29\x07\xa1\x04link"
            . "\x70\x00\x00\x00\x00"
            . "\x41"
            . "\x40\x40"
            . "\x00\x53\x28\xc0\x14\x09\xa1\x06source\x40\x40\x40\x40\x40\x40\x40"
            . "\x00\x53\x26\x45"
            . "\x40",
            $codec->encode(new Attach(
                name: 'link',
                handle: 0,
                role: LinkRole::Receiver,
                source: new Source(
                    address: 'source',
                    defaultOutcome: new Released(),
                ),
            )),
        );
    }

    public function testEncodesAttachWithModifiedSourceDefaultOutcome(): void
    {
        $codec = new AttachCodec();

        self::assertSame(
            "\x00\x53\x12\xc0\x29\x07\xa1\x04link"
            . "\x70\x00\x00\x00\x00"
            . "\x41"
            . "\x40\x40"
            . "\x00\x53\x28\xc0\x14\x09\xa1\x06source\x40\x40\x40\x40\x40\x40\x40"
            . "\x00\x53\x27\x45"
            . "\x40",
            $codec->encode(new Attach(
                name: 'link',
                handle: 0,
                role: LinkRole::Receiver,
                source: new Source(
                    address: 'source',
                    defaultOutcome: new Modified(),
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

    public function testDecodesAttachWithSourceAndTargetDynamicPropertiesAndCapabilities(): void
    {
        $codec = new AttachCodec();

        self::assertEquals(
            new Attach(
                name: 'link',
                handle: 0,
                role: LinkRole::Receiver,
                source: new Source(
                    address: 'source',
                    dynamic: true,
                    dynamicNodeProperties: new AmqpMap([
                        [new Symbol('x-opt-created'), new UInt(1700000000)],
                    ]),
                    capabilities: ['temporary-source'],
                ),
                target: new Target(
                    address: 'target',
                    dynamic: true,
                    dynamicNodeProperties: new AmqpMap([
                        [new Symbol('foo'), true],
                    ]),
                    capabilities: ['temporary-target'],
                ),
            ),
            $codec->decode(
                "\x00\x53\x12\xc0\x81\x07\xa1\x04link"
                . "\x70\x00\x00\x00\x00"
                . "\x41"
                . "\x40\x40"
                . "\x00\x53\x28\xc0\x3d\x0b\xa1\x06source\x40\x40\x40\x41"
                . "\xc1\x15\x02\xa3\x0dx-opt-created\x70\x65\x53\xf1\x00"
                . "\x40\x40\x40\x40"
                . "\xe0\x13\x01\xa3\x10temporary-source"
                . "\x00\x53\x29\xc0\x2b\x07\xa1\x06target\x40\x40\x40\x41"
                . "\xc1\x07\x02\xa3\x03foo\x41"
                . "\xe0\x13\x01\xa3\x10temporary-target",
            ),
        );
    }

    public function testDecodesAttachWithSourceDistributionModeAndOutcomes(): void
    {
        $codec = new AttachCodec();

        self::assertEquals(
            new Attach(
                name: 'link',
                handle: 0,
                role: LinkRole::Receiver,
                source: new Source(
                    address: 'source',
                    distributionMode: 'move',
                    outcomes: ['accepted', 'rejected'],
                ),
            ),
            $codec->decode(
                "\x00\x53\x12\xc0\x41\x07\xa1\x04link"
                . "\x70\x00\x00\x00\x00"
                . "\x41"
                . "\x40\x40"
                . "\x00\x53\x28\xc0\x2c\x0a\xa1\x06source\x40\x40\x40\x40\x40\xa3\x04move\x40\x40"
                . "\xe0\x14\x02\xa3\x08accepted\x08rejected"
                . "\x40",
            ),
        );
    }

    public function testDecodesAttachWithSourceFilter(): void
    {
        $codec = new AttachCodec();

        self::assertEquals(
            new Attach(
                name: 'link',
                handle: 0,
                role: LinkRole::Receiver,
                source: new Source(
                    address: 'source',
                    filter: new AmqpMap([
                        [new Symbol('selector'), "color = 'red'"],
                    ]),
                ),
            ),
            $codec->decode(
                "\x00\x53\x12\xc0\x40\x07\xa1\x04link"
                . "\x70\x00\x00\x00\x00"
                . "\x41"
                . "\x40\x40"
                . "\x00\x53\x28\xc0\x2b\x08\xa1\x06source\x40\x40\x40\x40\x40\x40"
                . "\xc1\x1a\x02\xa3\x08selector\xa1\x0dcolor = 'red'"
                . "\x40",
            ),
        );
    }

    public function testDecodesAttachWithSourceDefaultOutcome(): void
    {
        $codec = new AttachCodec();

        self::assertEquals(
            new Attach(
                name: 'link',
                handle: 0,
                role: LinkRole::Receiver,
                source: new Source(
                    address: 'source',
                    defaultOutcome: new Released(),
                ),
            ),
            $codec->decode(
                "\x00\x53\x12\xc0\x29\x07\xa1\x04link"
                . "\x70\x00\x00\x00\x00"
                . "\x41"
                . "\x40\x40"
                . "\x00\x53\x28\xc0\x14\x09\xa1\x06source\x40\x40\x40\x40\x40\x40\x40"
                . "\x00\x53\x26\x45"
                . "\x40",
            ),
        );
    }

    public function testDecodesAttachWithModifiedSourceDefaultOutcome(): void
    {
        $codec = new AttachCodec();

        self::assertEquals(
            new Attach(
                name: 'link',
                handle: 0,
                role: LinkRole::Receiver,
                source: new Source(
                    address: 'source',
                    defaultOutcome: new Modified(),
                ),
            ),
            $codec->decode(
                "\x00\x53\x12\xc0\x29\x07\xa1\x04link"
                . "\x70\x00\x00\x00\x00"
                . "\x41"
                . "\x40\x40"
                . "\x00\x53\x28\xc0\x14\x09\xa1\x06source\x40\x40\x40\x40\x40\x40\x40"
                . "\x00\x53\x27\x45"
                . "\x40",
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

    public function testEncodesAttachSettlementModes(): void
    {
        $codec = new AttachCodec();

        self::assertSame(
            "\x00\x53\x12\xc0\x2a\x0a\xa1\x06sender"
            . "\x70\x00\x00\x00\x00"
            . "\x42"
            . "\x50\x02"
            . "\x50\x00"
            . "\x40"
            . "\x00\x53\x29\xc0\x0e\x01\xa1\x0borders.test"
            . "\x40\x40\x43",
            $codec->encode(new Attach(
                name: 'sender',
                handle: 0,
                role: LinkRole::Sender,
                targetAddress: 'orders.test',
                initialDeliveryCount: 0,
                senderSettleMode: 2,
                receiverSettleMode: 0,
            )),
        );
    }

    public function testEncodesAttachMaxMessageSize(): void
    {
        $codec = new AttachCodec();

        self::assertSame(
            "\x00\x53\x12\xc0\x31\x0b\xa1\x06sender"
            . "\x70\x00\x00\x00\x00"
            . "\x42"
            . "\x40\x40\x40"
            . "\x00\x53\x29\xc0\x0e\x01\xa1\x0borders.test"
            . "\x40\x40\x43"
            . "\x80\x00\x00\x00\x00\x00\x01\x00\x00",
            $codec->encode(new Attach(
                name: 'sender',
                handle: 0,
                role: LinkRole::Sender,
                targetAddress: 'orders.test',
                initialDeliveryCount: 0,
                maxMessageSize: new ULong(65536),
            )),
        );
    }

    public function testEncodesAttachOfferedAndDesiredCapabilities(): void
    {
        $codec = new AttachCodec();

        self::assertSame(
            "\x00\x53\x12\xc0\x47\x0d\xa1\x06sender"
            . "\x70\x00\x00\x00\x00"
            . "\x42"
            . "\x40\x40\x40\x40\x40\x40\x43\x40"
            . "\xe0\x23\x02\xa3\x0fanonymous-relay\x10delayed-delivery"
            . "\xe0\x09\x01\xa3\x06shared",
            $codec->encode(new Attach(
                name: 'sender',
                handle: 0,
                role: LinkRole::Sender,
                initialDeliveryCount: 0,
                offeredCapabilities: ['anonymous-relay', 'delayed-delivery'],
                desiredCapabilities: ['shared'],
            )),
        );
    }

    public function testEncodesAttachProperties(): void
    {
        $codec = new AttachCodec();

        self::assertSame(
            "\x00\x53\x12\xc0\x38\x0e\xa1\x06sender"
            . "\x70\x00\x00\x00\x00"
            . "\x42"
            . "\x40\x40\x40\x40\x40\x40\x43\x40\x40\x40"
            . "\xc1\x1d\x04\xa3\x07product\xa1\x07sigbits\xa3\x03ttl\x70\x00\x00\x00\x3c",
            $codec->encode(new Attach(
                name: 'sender',
                handle: 0,
                role: LinkRole::Sender,
                initialDeliveryCount: 0,
                properties: new AmqpMap([
                    [new Symbol('product'), 'sigbits'],
                    [new Symbol('ttl'), new UInt(60)],
                ]),
            )),
        );
    }

    public function testEncodesAttachUnsettledFields(): void
    {
        $codec = new AttachCodec();

        self::assertSame(
            "\x00\x53\x12\xc0\x27\x0a\xa1\x06sender"
            . "\x70\x00\x00\x00\x00"
            . "\x42"
            . "\x40\x40\x40\x40"
            . "\xc1\x10\x02\xa3\x08delivery\x70\x00\x00\x00\x01"
            . "\x41"
            . "\x43",
            $codec->encode(new Attach(
                name: 'sender',
                handle: 0,
                role: LinkRole::Sender,
                unsettled: new AmqpMap([
                    [new Symbol('delivery'), new UInt(1)],
                ]),
                incompleteUnsettled: true,
                initialDeliveryCount: 0,
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

    public function testDecodesSenderAttachWithInitialDeliveryCount(): void
    {
        $codec = new AttachCodec();

        $attach = $codec->decode(
            "\x00\x53\x12\xc0\x28\x0a\xa1\x06sender"
            . "\x70\x00\x00\x00\x00"
            . "\x42"
            . "\x40\x40\x40"
            . "\x00\x53\x29\xc0\x0e\x01\xa1\x0borders.test"
            . "\x40\x40\x43",
        );

        self::assertEquals(new Attach(
            name: 'sender',
            handle: 0,
            role: LinkRole::Sender,
            targetAddress: 'orders.test',
            initialDeliveryCount: 0,
        ), $attach);
        self::assertSame(0, $attach->initialDeliveryCount);
    }

    public function testRejectsSenderAttachWithoutInitialDeliveryCount(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('AMQP sender attach requires initial-delivery-count.');

        new Attach(
            name: 'sender',
            handle: 0,
            role: LinkRole::Sender,
        );
    }

    public function testRejectsReceiverAttachWithInitialDeliveryCount(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('AMQP receiver attach must not include initial-delivery-count.');

        new Attach(
            name: 'receiver',
            handle: 1,
            role: LinkRole::Receiver,
            initialDeliveryCount: 0,
        );
    }

    public function testRejectsDecodedSenderAttachWithoutInitialDeliveryCount(): void
    {
        $codec = new AttachCodec();

        $this->expectException(PerformativeException::class);
        $this->expectExceptionMessage('Malformed AMQP attach performative.');

        $codec->decode(
            "\x00\x53\x12\xc0\x0f\x03\xa1\x06sender"
            . "\x70\x00\x00\x00\x00"
            . "\x42",
        );
    }

    public function testRejectsDecodedReceiverAttachWithInitialDeliveryCount(): void
    {
        $codec = new AttachCodec();

        $this->expectException(PerformativeException::class);
        $this->expectExceptionMessage('Malformed AMQP attach performative.');

        $codec->decode(
            "\x00\x53\x12\xc0\x18\x0a\xa1\x08receiver"
            . "\x70\x00\x00\x00\x01"
            . "\x41"
            . "\x40\x40\x40\x40\x40\x40"
            . "\x43",
        );
    }

    public function testDecodesAttachMaxMessageSize(): void
    {
        $codec = new AttachCodec();

        $attach = $codec->decode(
            "\x00\x53\x12\xc0\x31\x0b\xa1\x06sender"
            . "\x70\x00\x00\x00\x00"
            . "\x42"
            . "\x40\x40\x40"
            . "\x00\x53\x29\xc0\x0e\x01\xa1\x0borders.test"
            . "\x40\x40\x43"
            . "\x80\x00\x00\x00\x00\x00\x01\x00\x00",
        );

        self::assertSame(0, $attach->initialDeliveryCount);
        self::assertEquals(new ULong(65536), $attach->maxMessageSize);
    }

    public function testDecodesAttachOfferedAndDesiredCapabilities(): void
    {
        $codec = new AttachCodec();

        $attach = $codec->decode(
            "\x00\x53\x12\xc0\x47\x0d\xa1\x06sender"
            . "\x70\x00\x00\x00\x00"
            . "\x42"
            . "\x40\x40\x40\x40\x40\x40\x43\x40"
            . "\xe0\x23\x02\xa3\x0fanonymous-relay\x10delayed-delivery"
            . "\xe0\x09\x01\xa3\x06shared",
        );

        self::assertSame(['anonymous-relay', 'delayed-delivery'], $attach->offeredCapabilities);
        self::assertSame(['shared'], $attach->desiredCapabilities);
    }

    public function testDecodesAttachProperties(): void
    {
        $codec = new AttachCodec();

        $attach = $codec->decode(
            "\x00\x53\x12\xc0\x38\x0e\xa1\x06sender"
            . "\x70\x00\x00\x00\x00"
            . "\x42"
            . "\x40\x40\x40\x40\x40\x40\x43\x40\x40\x40"
            . "\xc1\x1d\x04\xa3\x07product\xa1\x07sigbits\xa3\x03ttl\x70\x00\x00\x00\x3c",
        );

        self::assertEquals(new AmqpMap([
            [new Symbol('product'), 'sigbits'],
            [new Symbol('ttl'), new UInt(60)],
        ]), $attach->properties);
    }

    public function testDecodesAttachUnsettledFields(): void
    {
        $codec = new AttachCodec();

        $attach = $codec->decode(
            "\x00\x53\x12\xc0\x27\x0a\xa1\x06sender"
            . "\x70\x00\x00\x00\x00"
            . "\x42"
            . "\x40\x40\x40\x40"
            . "\xc1\x10\x02\xa3\x08delivery\x70\x00\x00\x00\x01"
            . "\x41"
            . "\x43",
        );

        self::assertEquals(new AmqpMap([
            [new Symbol('delivery'), new UInt(1)],
        ]), $attach->unsettled);
        self::assertTrue($attach->incompleteUnsettled);
    }

    public function testDecodesReceiverAttachMaxMessageSizeWithOmittedInitialDeliveryCount(): void
    {
        $codec = new AttachCodec();

        $attach = $codec->decode(
            "\x00\x53\x12\xc0\x33\x0b\xa1\x08receiver"
            . "\x70\x00\x00\x00\x01"
            . "\x41"
            . "\x40\x40"
            . "\x00\x53\x28\xc0\x0e\x01\xa1\x0borders.test"
            . "\x40\x40\x40\x40"
            . "\x80\x00\x00\x00\x00\x00\x01\x00\x00",
        );

        self::assertNull($attach->initialDeliveryCount);
        self::assertEquals(new ULong(65536), $attach->maxMessageSize);
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
