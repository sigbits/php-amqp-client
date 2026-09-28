<?php

declare(strict_types=1);

namespace Sigbits\Amqp\Tests\Engine;

use PHPUnit\Framework\TestCase;
use Sigbits\Amqp\Engine\SessionEngine;
use Sigbits\Amqp\Engine\SessionEvent;
use Sigbits\Amqp\Engine\SessionException;
use Sigbits\Amqp\Engine\SessionState;

final class SessionEngineTest extends TestCase
{
    public function testBeginEmitsBeginFrameAndTransitionsToBeginSent(): void
    {
        $engine = new SessionEngine(localChannel: 1);

        self::assertSame(
            [
                "\x00\x00\x00\x1e\x02\x00\x00\x01"
                . "\x00\x53\x11\xc0\x11\x04\x40"
                . "\x70\x00\x00\x00\x00"
                . "\x70\x7f\xff\xff\xff"
                . "\x70\x7f\xff\xff\xff",
            ],
            $engine->begin(),
        );
        self::assertSame(SessionState::BeginSent, $engine->state());
    }

    public function testRemoteBeginTransitionsSessionToMapped(): void
    {
        $engine = new SessionEngine(localChannel: 1);
        $engine->begin();

        self::assertSame(
            [SessionEvent::SessionMapped],
            $engine->push(
                "\x00\x00\x00\x1e\x02\x00\x00\x01"
                . "\x00\x53\x11\xc0\x11\x04\x40"
                . "\x70\x00\x00\x00\x00"
                . "\x70\x7f\xff\xff\xff"
                . "\x70\x7f\xff\xff\xff",
            ),
        );
        self::assertSame(SessionState::Mapped, $engine->state());
    }

    public function testEndEmitsEndFrameAndTransitionsToEndSent(): void
    {
        $engine = new SessionEngine(localChannel: 1);
        $engine->begin();
        $engine->push(
            "\x00\x00\x00\x1e\x02\x00\x00\x01"
            . "\x00\x53\x11\xc0\x11\x04\x40"
            . "\x70\x00\x00\x00\x00"
            . "\x70\x7f\xff\xff\xff"
            . "\x70\x7f\xff\xff\xff",
        );

        self::assertSame(
            ["\x00\x00\x00\x0c\x02\x00\x00\x01\x00\x53\x17\x45"],
            $engine->end(),
        );
        self::assertSame(SessionState::EndSent, $engine->state());
    }

    public function testRemoteEndTransitionsSessionToEnded(): void
    {
        $engine = new SessionEngine(localChannel: 1);
        $engine->begin();
        $engine->push(
            "\x00\x00\x00\x1e\x02\x00\x00\x01"
            . "\x00\x53\x11\xc0\x11\x04\x40"
            . "\x70\x00\x00\x00\x00"
            . "\x70\x7f\xff\xff\xff"
            . "\x70\x7f\xff\xff\xff",
        );
        $engine->end();

        self::assertSame(
            [SessionEvent::SessionEnded],
            $engine->push("\x00\x00\x00\x0c\x02\x00\x00\x01\x00\x53\x17\x45"),
        );
        self::assertSame(SessionState::Ended, $engine->state());
    }

    public function testRejectsBeginWhenSessionAlreadyStarted(): void
    {
        $engine = new SessionEngine(localChannel: 1);
        $engine->begin();

        $this->expectException(SessionException::class);
        $this->expectExceptionMessage('Cannot begin AMQP session from state BeginSent.');

        $engine->begin();
    }

    public function testRejectsEndBeforeSessionIsMapped(): void
    {
        $engine = new SessionEngine(localChannel: 1);

        $this->expectException(SessionException::class);
        $this->expectExceptionMessage('Cannot end AMQP session from state Idle.');

        $engine->end();
    }

    public function testRejectsUnsupportedSessionPerformative(): void
    {
        $engine = new SessionEngine(localChannel: 1);
        $engine->begin();

        $this->expectException(SessionException::class);
        $this->expectExceptionMessage('Unsupported AMQP session performative.');

        $engine->push("\x00\x00\x00\x0c\x02\x00\x00\x01\x00\x53\x10\x45");
    }
}
