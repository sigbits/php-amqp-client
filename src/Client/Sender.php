<?php

declare(strict_types=1);

namespace Sigbits\Amqp\Client;

use Closure;
use Sigbits\Amqp\Engine\SenderLinkEngine;
use Sigbits\Amqp\Engine\SenderLinkState;
use Sigbits\Amqp\Protocol\Message\Message;

final class Sender
{
    /**
     * @param callable(list<string>): void $writeAll
     * @param callable(): string $readFrame
     */
    public function __construct(
        private readonly SenderLinkEngine $engine,
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

    public function state(): SenderLinkState
    {
        return $this->engine->state();
    }

    public function availableCredit(): int
    {
        return $this->engine->availableCredit();
    }

    public function send(Message|string $message, int $maxFrameSize = 512): void
    {
        while ($this->engine->availableCredit() <= 0) {
            $this->engine->push(($this->readFrame)());
        }

        ($this->writeAll)($this->engine->transfer(
            $message instanceof Message ? $message : new Message(body: $message),
            maxFrameSize: $maxFrameSize,
        ));
    }
}
