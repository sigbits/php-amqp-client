<?php

declare(strict_types=1);

namespace Sigbits\Amqp\Tests\Engine;

use PHPUnit\Framework\TestCase;
use Sigbits\Amqp\Engine\ReceiverLinkEngine;
use Sigbits\Amqp\Engine\ReceiverLinkEvent;
use Sigbits\Amqp\Engine\ReceiverLinkException;
use Sigbits\Amqp\Engine\ReceiverLinkState;
use Sigbits\Amqp\Protocol\Message\Message;

final class ReceiverLinkEngineTest extends TestCase
{
    public function testAttachEmitsReceiverAttachFrameAndTransitionsToAttachSent(): void
    {
        $engine = new ReceiverLinkEngine(sessionChannel: 1, name: 'receiver', handle: 0);

        self::assertSame(
            [
                "\x00\x00\x00\x1e\x02\x00\x00\x01"
                . "\x00\x53\x12\xc0\x11\x03\xa1\x08receiver"
                . "\x70\x00\x00\x00\x00"
                . "\x41",
            ],
            $engine->attach(),
        );
        self::assertSame(ReceiverLinkState::AttachSent, $engine->state());
    }

    public function testRemoteSenderAttachTransitionsLinkToAttached(): void
    {
        $engine = new ReceiverLinkEngine(sessionChannel: 1, name: 'receiver', handle: 0);
        $engine->attach();

        self::assertSame(
            [ReceiverLinkEvent::LinkAttached],
            $engine->push(
                "\x00\x00\x00\x1e\x02\x00\x00\x01"
                . "\x00\x53\x12\xc0\x11\x03\xa1\x08receiver"
                . "\x70\x00\x00\x00\x00"
                . "\x42",
            ),
        );
        self::assertSame(ReceiverLinkState::Attached, $engine->state());
    }

    public function testRemoteTransferCompletesMessageAndQueuesItForReceive(): void
    {
        $engine = new ReceiverLinkEngine(sessionChannel: 1, name: 'receiver', handle: 0);
        $engine->attach();
        $engine->push(
            "\x00\x00\x00\x1e\x02\x00\x00\x01"
            . "\x00\x53\x12\xc0\x11\x03\xa1\x08receiver"
            . "\x70\x00\x00\x00\x00"
            . "\x42",
        );

        self::assertSame(
            [ReceiverLinkEvent::MessageReceived],
            $engine->push(
                "\x00\x00\x00\x35\x02\x00\x00\x01"
                . "\x00\x53\x14\xc0\x1e\x06"
                . "\x70\x00\x00\x00\x00"
                . "\x70\x00\x00\x00\x00"
                . "\xa0\x0adelivery-0"
                . "\x70\x00\x00\x00\x00"
                . "\x40"
                . "\x42"
                . "\x00\x53\x75\xa0\x05hello",
            ),
        );
        self::assertEquals(new Message(body: 'hello'), $engine->receive());
        self::assertNull($engine->receive());
    }

    public function testAcceptEmitsAcceptedDispositionFrame(): void
    {
        $engine = new ReceiverLinkEngine(sessionChannel: 1, name: 'receiver', handle: 0);

        self::assertSame(
            [
                "\x00\x00\x00\x1a\x02\x00\x00\x01"
                . "\x00\x53\x15\xc0\x0d\x05"
                . "\x41"
                . "\x70\x00\x00\x00\x00"
                . "\x40"
                . "\x41"
                . "\x00\x53\x24\x45",
            ],
            $engine->accept(deliveryId: 0),
        );
    }

    public function testReleaseEmitsReleasedDispositionFrame(): void
    {
        $engine = new ReceiverLinkEngine(sessionChannel: 1, name: 'receiver', handle: 0);

        self::assertSame(
            [
                "\x00\x00\x00\x1a\x02\x00\x00\x01"
                . "\x00\x53\x15\xc0\x0d\x05"
                . "\x41"
                . "\x70\x00\x00\x00\x01"
                . "\x40"
                . "\x41"
                . "\x00\x53\x26\x45",
            ],
            $engine->release(deliveryId: 1),
        );
    }

    public function testRejectEmitsRejectedDispositionFrame(): void
    {
        $engine = new ReceiverLinkEngine(sessionChannel: 1, name: 'receiver', handle: 0);

        self::assertSame(
            [
                "\x00\x00\x00\x1a\x02\x00\x00\x01"
                . "\x00\x53\x15\xc0\x0d\x05"
                . "\x41"
                . "\x70\x00\x00\x00\x02"
                . "\x40"
                . "\x41"
                . "\x00\x53\x25\x45",
            ],
            $engine->reject(deliveryId: 2),
        );
    }

    public function testRemoteFragmentedTransferCompletesMessageAfterFinalFrame(): void
    {
        $engine = new ReceiverLinkEngine(sessionChannel: 1, name: 'receiver', handle: 0);
        $engine->attach();
        $engine->push(
            "\x00\x00\x00\x1e\x02\x00\x00\x01"
            . "\x00\x53\x12\xc0\x11\x03\xa1\x08receiver"
            . "\x70\x00\x00\x00\x00"
            . "\x42",
        );

        self::assertSame(
            [],
            $engine->push(
                "\x00\x00\x00\x32\x02\x00\x00\x01"
                . "\x00\x53\x14\xc0\x1e\x06"
                . "\x70\x00\x00\x00\x00"
                . "\x70\x00\x00\x00\x00"
                . "\xa0\x0adelivery-0"
                . "\x70\x00\x00\x00\x00"
                . "\x40"
                . "\x41"
                . "\x00\x53\x75\xa0\x0cab",
            ),
        );
        self::assertNull($engine->receive());

        self::assertSame(
            [],
            $engine->push(
                "\x00\x00\x00\x32\x02\x00\x00\x01"
                . "\x00\x53\x14\xc0\x1e\x06"
                . "\x70\x00\x00\x00\x00"
                . "\x70\x00\x00\x00\x00"
                . "\xa0\x0adelivery-0"
                . "\x70\x00\x00\x00\x00"
                . "\x40"
                . "\x41"
                . "cdefghi",
            ),
        );
        self::assertNull($engine->receive());

        self::assertSame(
            [ReceiverLinkEvent::MessageReceived],
            $engine->push(
                "\x00\x00\x00\x2e\x02\x00\x00\x01"
                . "\x00\x53\x14\xc0\x1e\x06"
                . "\x70\x00\x00\x00\x00"
                . "\x70\x00\x00\x00\x00"
                . "\xa0\x0adelivery-0"
                . "\x70\x00\x00\x00\x00"
                . "\x40"
                . "\x42"
                . "jkl",
            ),
        );
        self::assertEquals(new Message(body: 'abcdefghijkl'), $engine->receive());
    }

    public function testDetachEmitsDetachFrameAndTransitionsToDetachSent(): void
    {
        $engine = new ReceiverLinkEngine(sessionChannel: 1, name: 'receiver', handle: 0);
        $engine->attach();
        $engine->push(
            "\x00\x00\x00\x1e\x02\x00\x00\x01"
            . "\x00\x53\x12\xc0\x11\x03\xa1\x08receiver"
            . "\x70\x00\x00\x00\x00"
            . "\x42",
        );

        self::assertSame(
            ["\x00\x00\x00\x13\x02\x00\x00\x01\x00\x53\x16\xc0\x06\x01\x70\x00\x00\x00\x00"],
            $engine->detach(),
        );
        self::assertSame(ReceiverLinkState::DetachSent, $engine->state());
    }

    public function testRemoteDetachTransitionsLinkToDetached(): void
    {
        $engine = new ReceiverLinkEngine(sessionChannel: 1, name: 'receiver', handle: 0);
        $engine->attach();
        $engine->push(
            "\x00\x00\x00\x1e\x02\x00\x00\x01"
            . "\x00\x53\x12\xc0\x11\x03\xa1\x08receiver"
            . "\x70\x00\x00\x00\x00"
            . "\x42",
        );
        $engine->detach();

        self::assertSame(
            [ReceiverLinkEvent::LinkDetached],
            $engine->push("\x00\x00\x00\x13\x02\x00\x00\x01\x00\x53\x16\xc0\x06\x01\x70\x00\x00\x00\x00"),
        );
        self::assertSame(ReceiverLinkState::Detached, $engine->state());
    }

    public function testRejectsAttachWhenLinkAlreadyStarted(): void
    {
        $engine = new ReceiverLinkEngine(sessionChannel: 1, name: 'receiver', handle: 0);
        $engine->attach();

        $this->expectException(ReceiverLinkException::class);
        $this->expectExceptionMessage('Cannot attach AMQP receiver link from state AttachSent.');

        $engine->attach();
    }

    public function testRejectsDetachBeforeLinkIsAttached(): void
    {
        $engine = new ReceiverLinkEngine(sessionChannel: 1, name: 'receiver', handle: 0);

        $this->expectException(ReceiverLinkException::class);
        $this->expectExceptionMessage('Cannot detach AMQP receiver link from state Idle.');

        $engine->detach();
    }

    public function testRejectsUnsupportedReceiverLinkPerformative(): void
    {
        $engine = new ReceiverLinkEngine(sessionChannel: 1, name: 'receiver', handle: 0);
        $engine->attach();

        $this->expectException(ReceiverLinkException::class);
        $this->expectExceptionMessage('Unsupported AMQP receiver link performative.');

        $engine->push("\x00\x00\x00\x0c\x02\x00\x00\x01\x00\x53\x11\x45");
    }
}
