<?php

declare(strict_types=1);

namespace Sigbits\Amqp\Tests\Engine;

use PHPUnit\Framework\TestCase;
use Sigbits\Amqp\Engine\SenderLinkEngine;
use Sigbits\Amqp\Engine\SenderLinkEvent;
use Sigbits\Amqp\Engine\SenderLinkException;
use Sigbits\Amqp\Engine\SenderLinkState;

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
