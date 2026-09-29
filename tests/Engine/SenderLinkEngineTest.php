<?php

declare(strict_types=1);

namespace Sigbits\Amqp\Tests\Engine;

use PHPUnit\Framework\TestCase;
use Sigbits\Amqp\Engine\SenderLinkEngine;
use Sigbits\Amqp\Engine\SenderLinkEvent;
use Sigbits\Amqp\Engine\SenderLinkException;
use Sigbits\Amqp\Engine\SenderLinkState;
use Sigbits\Amqp\Protocol\Message\Message;
use Sigbits\Amqp\Protocol\Message\MessageCodec;
use Sigbits\Amqp\Protocol\Performative\Transfer;
use Sigbits\Amqp\Protocol\Performative\TransferCodec;

final class SenderLinkEngineTest extends TestCase
{
    public function testAttachEmitsSenderAttachFrameAndTransitionsToAttachSent(): void
    {
        $engine = new SenderLinkEngine(sessionChannel: 1, name: 'sender', handle: 0);

        self::assertSame(
            [
                "\x00\x00\x00\x1c\x02\x00\x00\x01"
                . "\x00\x53\x12\xc0\x0f\x03\xa1\x06sender"
                . "\x70\x00\x00\x00\x00"
                . "\x42",
            ],
            $engine->attach(),
        );
        self::assertSame(SenderLinkState::AttachSent, $engine->state());
    }

    public function testAttachCanIncludeTargetAddress(): void
    {
        $engine = new SenderLinkEngine(
            sessionChannel: 1,
            name: 'sender',
            handle: 0,
            targetAddress: 'orders.test',
        );

        self::assertSame(
            [
                "\x00\x00\x00\x35\x02\x00\x00\x01"
                . "\x00\x53\x12\xc0\x28\x0a\xa1\x06sender"
                . "\x70\x00\x00\x00\x00"
                . "\x42"
                . "\x40\x40\x40"
                . "\x00\x53\x29\xc0\x0e\x01\xa1\x0borders.test"
                . "\x40\x40\x43",
            ],
            $engine->attach(),
        );
    }

    public function testAttachCanIncludeSourceAndTargetAddress(): void
    {
        $engine = new SenderLinkEngine(
            sessionChannel: 1,
            name: 'sender',
            handle: 0,
            targetAddress: 'orders.test',
            sourceAddress: 'orders.test',
        );

        self::assertSame(
            [
                "\x00\x00\x00\x47\x02\x00\x00\x01"
                . "\x00\x53\x12\xc0\x3a\x0a\xa1\x06sender"
                . "\x70\x00\x00\x00\x00"
                . "\x42"
                . "\x40\x40"
                . "\x00\x53\x28\xc0\x0e\x01\xa1\x0borders.test"
                . "\x00\x53\x29\xc0\x0e\x01\xa1\x0borders.test"
                . "\x40\x40\x43",
            ],
            $engine->attach(),
        );
    }

    public function testRemoteReceiverAttachTransitionsLinkToAttached(): void
    {
        $engine = new SenderLinkEngine(sessionChannel: 1, name: 'sender', handle: 0);
        $engine->attach();

        self::assertSame(
            [SenderLinkEvent::LinkAttached],
            $engine->push(
                "\x00\x00\x00\x1c\x02\x00\x00\x01"
                . "\x00\x53\x12\xc0\x0f\x03\xa1\x06sender"
                . "\x70\x00\x00\x00\x00"
                . "\x41",
            ),
        );
        self::assertSame(SenderLinkState::Attached, $engine->state());
    }

    public function testRemoteFlowUpdatesAvailableLinkCredit(): void
    {
        $engine = new SenderLinkEngine(sessionChannel: 1, name: 'sender', handle: 0);
        $engine->attach();
        $engine->push(
            "\x00\x00\x00\x1c\x02\x00\x00\x01"
            . "\x00\x53\x12\xc0\x0f\x03\xa1\x06sender"
            . "\x70\x00\x00\x00\x00"
            . "\x41",
        );

        self::assertSame(
            [SenderLinkEvent::LinkCreditUpdated],
            $engine->push(
                "\x00\x00\x00\x21\x02\x00\x00\x01"
                . "\x00\x53\x13\xc0\x14\x07"
                . "\x40\x40\x40\x40"
                . "\x70\x00\x00\x00\x00"
                . "\x70\x00\x00\x00\x00"
                . "\x70\x00\x00\x00\x02",
            ),
        );
        self::assertSame(2, $engine->availableCredit());
    }

    public function testClaimCreditReturnsDeliveryIdAndConsumesAvailableCredit(): void
    {
        $engine = new SenderLinkEngine(sessionChannel: 1, name: 'sender', handle: 0);
        $engine->attach();
        $engine->push(
            "\x00\x00\x00\x1c\x02\x00\x00\x01"
            . "\x00\x53\x12\xc0\x0f\x03\xa1\x06sender"
            . "\x70\x00\x00\x00\x00"
            . "\x41",
        );
        $engine->push(
            "\x00\x00\x00\x21\x02\x00\x00\x01"
            . "\x00\x53\x13\xc0\x14\x07"
            . "\x40\x40\x40\x40"
            . "\x70\x00\x00\x00\x00"
            . "\x70\x00\x00\x00\x00"
            . "\x70\x00\x00\x00\x02",
        );

        self::assertSame(0, $engine->claimCredit());
        self::assertSame(1, $engine->availableCredit());
        self::assertSame(1, $engine->claimCredit());
        self::assertSame(0, $engine->availableCredit());
    }

    public function testTransferEmitsMessageFrameAndConsumesCredit(): void
    {
        $engine = new SenderLinkEngine(sessionChannel: 1, name: 'sender', handle: 0);
        $engine->attach();
        $engine->push(
            "\x00\x00\x00\x1c\x02\x00\x00\x01"
            . "\x00\x53\x12\xc0\x0f\x03\xa1\x06sender"
            . "\x70\x00\x00\x00\x00"
            . "\x41",
        );
        $engine->push(
            "\x00\x00\x00\x21\x02\x00\x00\x01"
            . "\x00\x53\x13\xc0\x14\x07"
            . "\x40\x40\x40\x40"
            . "\x70\x00\x00\x00\x00"
            . "\x70\x00\x00\x00\x00"
            . "\x70\x00\x00\x00\x01",
        );

        self::assertSame(
            [
                "\x00\x00\x00\x35\x02\x00\x00\x01"
                . "\x00\x53\x14\xc0\x1e\x06"
                . "\x70\x00\x00\x00\x00"
                . "\x70\x00\x00\x00\x00"
                . "\xa0\x0adelivery-0"
                . "\x70\x00\x00\x00\x00"
                . "\x40"
                . "\x42"
                . "\x00\x53\x75\xa0\x05hello",
            ],
            $engine->transfer(new Message(body: 'hello')),
        );
        self::assertSame(0, $engine->availableCredit());
    }

    public function testTransferFragmentsMessagePayloadWhenFrameBudgetRequiresIt(): void
    {
        $engine = new SenderLinkEngine(sessionChannel: 1, name: 'sender', handle: 0);
        $engine->attach();
        $engine->push(
            "\x00\x00\x00\x1c\x02\x00\x00\x01"
            . "\x00\x53\x12\xc0\x0f\x03\xa1\x06sender"
            . "\x70\x00\x00\x00\x00"
            . "\x41",
        );
        $engine->push(
            "\x00\x00\x00\x21\x02\x00\x00\x01"
            . "\x00\x53\x13\xc0\x14\x07"
            . "\x40\x40\x40\x40"
            . "\x70\x00\x00\x00\x00"
            . "\x70\x00\x00\x00\x00"
            . "\x70\x00\x00\x00\x01",
        );
        $transferCodec = new TransferCodec();
        $messageCodec = new MessageCodec();

        $frames = $engine->transfer(new Message(body: 'abcdefghijkl'), maxFrameSize: 50);

        self::assertCount(3, $frames);
        self::assertStringStartsWith(
            "\x00\x00\x00\x32\x02\x00\x00\x01"
            . $transferCodec->encode(new Transfer(
                handle: 0,
                deliveryId: 0,
                deliveryTag: 'delivery-0',
                messageFormat: 0,
                more: true,
            )),
            $frames[0],
        );
        self::assertStringStartsWith(
            "\x00\x00\x00\x32\x02\x00\x00\x01"
            . $transferCodec->encode(new Transfer(
                handle: 0,
                deliveryId: 0,
                deliveryTag: 'delivery-0',
                messageFormat: 0,
                more: true,
            )),
            $frames[1],
        );
        self::assertStringStartsWith(
            "\x00\x00\x00\x2e\x02\x00\x00\x01"
            . $transferCodec->encode(new Transfer(
                handle: 0,
                deliveryId: 0,
                deliveryTag: 'delivery-0',
                messageFormat: 0,
                more: false,
            )),
            $frames[2],
        );

        self::assertSame(
            $messageCodec->encode(new Message(body: 'abcdefghijkl')),
            substr($frames[0], 43) . substr($frames[1], 43) . substr($frames[2], 43),
        );
    }

    public function testRejectsClaimCreditWhenNoCreditIsAvailable(): void
    {
        $engine = new SenderLinkEngine(sessionChannel: 1, name: 'sender', handle: 0);

        $this->expectException(SenderLinkException::class);
        $this->expectExceptionMessage('Cannot claim AMQP sender link credit when none is available.');

        $engine->claimCredit();
    }

    public function testRejectsTransferWhenNoCreditIsAvailable(): void
    {
        $engine = new SenderLinkEngine(sessionChannel: 1, name: 'sender', handle: 0);

        $this->expectException(SenderLinkException::class);
        $this->expectExceptionMessage('Cannot claim AMQP sender link credit when none is available.');

        $engine->transfer(new Message(body: 'hello'));
    }

    public function testDetachEmitsDetachFrameAndTransitionsToDetachSent(): void
    {
        $engine = new SenderLinkEngine(sessionChannel: 1, name: 'sender', handle: 0);
        $engine->attach();
        $engine->push(
            "\x00\x00\x00\x1c\x02\x00\x00\x01"
            . "\x00\x53\x12\xc0\x0f\x03\xa1\x06sender"
            . "\x70\x00\x00\x00\x00"
            . "\x41",
        );

        self::assertSame(
            ["\x00\x00\x00\x13\x02\x00\x00\x01\x00\x53\x16\xc0\x06\x01\x70\x00\x00\x00\x00"],
            $engine->detach(),
        );
        self::assertSame(SenderLinkState::DetachSent, $engine->state());
    }

    public function testRemoteDetachTransitionsLinkToDetached(): void
    {
        $engine = new SenderLinkEngine(sessionChannel: 1, name: 'sender', handle: 0);
        $engine->attach();
        $engine->push(
            "\x00\x00\x00\x1c\x02\x00\x00\x01"
            . "\x00\x53\x12\xc0\x0f\x03\xa1\x06sender"
            . "\x70\x00\x00\x00\x00"
            . "\x41",
        );
        $engine->detach();

        self::assertSame(
            [SenderLinkEvent::LinkDetached],
            $engine->push("\x00\x00\x00\x13\x02\x00\x00\x01\x00\x53\x16\xc0\x06\x01\x70\x00\x00\x00\x00"),
        );
        self::assertSame(SenderLinkState::Detached, $engine->state());
    }

    public function testRejectsAttachWhenLinkAlreadyStarted(): void
    {
        $engine = new SenderLinkEngine(sessionChannel: 1, name: 'sender', handle: 0);
        $engine->attach();

        $this->expectException(SenderLinkException::class);
        $this->expectExceptionMessage('Cannot attach AMQP sender link from state AttachSent.');

        $engine->attach();
    }

    public function testRejectsDetachBeforeLinkIsAttached(): void
    {
        $engine = new SenderLinkEngine(sessionChannel: 1, name: 'sender', handle: 0);

        $this->expectException(SenderLinkException::class);
        $this->expectExceptionMessage('Cannot detach AMQP sender link from state Idle.');

        $engine->detach();
    }

    public function testRejectsUnsupportedSenderLinkPerformative(): void
    {
        $engine = new SenderLinkEngine(sessionChannel: 1, name: 'sender', handle: 0);
        $engine->attach();

        $this->expectException(SenderLinkException::class);
        $this->expectExceptionMessage('Unsupported AMQP sender link performative.');

        $engine->push("\x00\x00\x00\x0c\x02\x00\x00\x01\x00\x53\x11\x45");
    }
}
