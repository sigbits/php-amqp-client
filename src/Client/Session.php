<?php

declare(strict_types=1);

namespace Sigbits\Amqp\Client;

use Closure;
use Sigbits\Amqp\Engine\SenderLinkEngine;
use Sigbits\Amqp\Engine\SenderLinkState;
use Sigbits\Amqp\Engine\SessionEngine;
use Sigbits\Amqp\Engine\SessionState;

final class Session
{
    /**
     * @param callable(list<string>): void $writeAll
     * @param callable(): string $readFrame
     */
    public function __construct(
        private readonly SessionEngine $engine,
        private readonly int $channel,
        callable $writeAll,
        callable $readFrame,
    ) {
        $this->writeAll = $writeAll(...);
        $this->readFrame = $readFrame(...);
    }

    /**
     * @var Closure(list<string>): void
     */
    private readonly Closure $writeAll;

    /**
     * @var Closure(): string
     */
    private readonly Closure $readFrame;

    public function state(): SessionState
    {
        return $this->engine->state();
    }

    public function openSender(string $address, string $name = 'sender', int $handle = 0): Sender
    {
        $engine = new SenderLinkEngine(
            sessionChannel: $this->channel,
            name: $name,
            handle: $handle,
            targetAddress: $address,
        );

        ($this->writeAll)($engine->attach());

        while ($engine->state() !== SenderLinkState::Attached || $engine->availableCredit() <= 0) {
            $engine->push(($this->readFrame)());
        }

        return new Sender($engine, $this->writeAll, $this->readFrame);
    }

    public function end(): void
    {
        ($this->writeAll)($this->engine->end());

        while ($this->engine->state() !== SessionState::Ended) {
            $frame = ($this->readFrame)();

            if (substr($frame, 8, 3) === "\x00\x53\x17") {
                $this->engine->push($frame);
            }
        }
    }
}
