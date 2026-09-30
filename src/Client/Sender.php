<?php

declare(strict_types=1);

namespace Sigbits\Amqp\Client;

use Closure;
use Sigbits\Amqp\Engine\SenderLinkEngine;
use Sigbits\Amqp\Engine\SenderLinkState;
use Sigbits\Amqp\Protocol\Message\Message;
use Sigbits\Amqp\Transport\TransportException;

final class Sender
{
    /**
     * @param callable(list<string>): void $writeAll
     * @param callable(): string $readFrame
     */
    private function __construct(
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
        if ($this->engine->state() === SenderLinkState::Detached) {
            throw ClientException::senderLinkDetached();
        }

        while ($this->engine->availableCredit() <= 0) {
            try {
                $this->engine->push(($this->readFrame)());
            } catch (TransportException $exception) {
                throw ClientException::senderLinkCreditExhausted($exception);
            }

            if ($this->engine->state() === SenderLinkState::Detached) {
                throw ClientException::senderLinkDetached();
            }
        }

        ($this->writeAll)($this->engine->transfer(
            $message instanceof Message ? $message : new Message(body: $message),
            maxFrameSize: $maxFrameSize,
        ));
    }

    public function detach(): void
    {
        if ($this->engine->state() === SenderLinkState::Detached) {
            return;
        }

        ($this->writeAll)($this->engine->detach());

        while ($this->engine->state() !== SenderLinkState::Detached) {
            $this->engine->push(($this->readFrame)());
        }
    }
}
