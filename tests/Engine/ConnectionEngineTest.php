<?php

declare(strict_types=1);

namespace Sigbits\Amqp\Tests\Engine;

use PHPUnit\Framework\TestCase;
use Sigbits\Amqp\Engine\ConnectionEngine;
use Sigbits\Amqp\Engine\ConnectionEvent;
use Sigbits\Amqp\Engine\ConnectionState;

final class ConnectionEngineTest extends TestCase
{
    public function testStartEmitsProtocolHeaderAndOpenFrame(): void
    {
        $engine = new ConnectionEngine(localContainerId: 'client');

        self::assertSame(
            [
                "AMQP\x00\x01\x00\x00",
                "\x00\x00\x00\x16\x02\x00\x00\x00\x00\x53\x10\xc0\x09\x01\xa1\x06client",
            ],
            $engine->start(),
        );
        self::assertSame(ConnectionState::OpenSent, $engine->state());
    }

    public function testRemoteHeaderAndOpenTransitionConnectionToOpened(): void
    {
        $engine = new ConnectionEngine(localContainerId: 'client');
        $engine->start();

        self::assertSame(
            [ConnectionEvent::ConnectionOpened],
            $engine->push(
                "AMQP\x00\x01\x00\x00"
                . "\x00\x00\x00\x16\x02\x00\x00\x00\x00\x53\x10\xc0\x09\x01\xa1\x06server",
            ),
        );
        self::assertSame(ConnectionState::Opened, $engine->state());
    }

    public function testRemoteInputCanBeFragmentedAcrossProtocolHeaderAndFrame(): void
    {
        $engine = new ConnectionEngine(localContainerId: 'client');
        $engine->start();

        self::assertSame([], $engine->push("AMQP\x00"));
        self::assertSame([], $engine->push("\x01\x00\x00\x00\x00"));

        self::assertSame(
            [ConnectionEvent::ConnectionOpened],
            $engine->push("\x00\x16\x02\x00\x00\x00\x00\x53\x10\xc0\x09\x01\xa1\x06server"),
        );
    }

    public function testCloseEmitsCloseFrameAndTransitionsToCloseSent(): void
    {
        $engine = new ConnectionEngine(localContainerId: 'client');
        $engine->start();
        $engine->push(
            "AMQP\x00\x01\x00\x00"
            . "\x00\x00\x00\x16\x02\x00\x00\x00\x00\x53\x10\xc0\x09\x01\xa1\x06server",
        );

        self::assertSame(
            ["\x00\x00\x00\x0c\x02\x00\x00\x00\x00\x53\x18\x45"],
            $engine->close(),
        );
        self::assertSame(ConnectionState::CloseSent, $engine->state());
    }

    public function testRemoteCloseTransitionsConnectionToClosed(): void
    {
        $engine = new ConnectionEngine(localContainerId: 'client');
        $engine->start();
        $engine->push(
            "AMQP\x00\x01\x00\x00"
            . "\x00\x00\x00\x16\x02\x00\x00\x00\x00\x53\x10\xc0\x09\x01\xa1\x06server",
        );

        self::assertSame(
            [ConnectionEvent::ConnectionClosed],
            $engine->push("\x00\x00\x00\x0c\x02\x00\x00\x00\x00\x53\x18\x45"),
        );
        self::assertSame(ConnectionState::Closed, $engine->state());
    }
}
