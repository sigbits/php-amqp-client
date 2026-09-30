<?php

declare(strict_types=1);

namespace Sigbits\Amqp\Client;

use Closure;
use Sigbits\Amqp\Client\Internal\ClientObjectFactory;
use Sigbits\Amqp\Engine\ReceiverLinkEngine;
use Sigbits\Amqp\Engine\ReceiverLinkState;
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
    private function __construct(
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
            sourceAddress: $address,
        );

        ($this->writeAll)($engine->attach());

        while ($engine->state() !== SenderLinkState::Attached || $engine->availableCredit() <= 0) {
            $frame = ($this->readFrame)();

            $this->failIfRemoteSessionEnded($frame);

            if ($this->isAttach($frame) || $this->isDetach($frame) || $this->isFlow($frame)) {
                $engine->push($frame);
            }

            if ($engine->state() === SenderLinkState::Detached) {
                throw ClientException::senderLinkDetachedDuringOpen($engine->lastRemoteDetachError());
            }
        }

        return ClientObjectFactory::sender($engine, $this->writeAll, $this->readFrame);
    }

    public function openReceiver(string $address, string $name = 'receiver', int $handle = 1, int $credit = 1): Receiver
    {
        $engine = new ReceiverLinkEngine(
            sessionChannel: $this->channel,
            name: $name,
            handle: $handle,
            sourceAddress: $address,
            targetAddress: $address,
        );

        ($this->writeAll)($engine->attach());

        while ($engine->state() !== ReceiverLinkState::Attached) {
            $frame = ($this->readFrame)();

            $this->failIfRemoteSessionEnded($frame);

            if ($this->isAttach($frame) || $this->isDetach($frame)) {
                $engine->push($frame);
            }

            if ($engine->state() === ReceiverLinkState::Detached) {
                throw ClientException::receiverLinkDetachedDuringOpen($engine->lastRemoteDetachError());
            }
        }

        ($this->writeAll)($engine->grantCredit(deliveryCount: 0, linkCredit: $credit));

        return ClientObjectFactory::receiver(
            $engine,
            fn (int $_timeoutMilliseconds): string => ($this->readFrame)(),
            $this->writeAll,
        );
    }

    public function end(): void
    {
        if ($this->engine->state() === SessionState::Ended) {
            return;
        }

        ($this->writeAll)($this->engine->end());

        while ($this->engine->state() !== SessionState::Ended) {
            $frame = ($this->readFrame)();

            if (substr($frame, 8, 3) === "\x00\x53\x17") {
                $this->engine->push($frame);
            }
        }
    }

    private function isAttach(string $frame): bool
    {
        return substr($frame, 8, 3) === "\x00\x53\x12";
    }

    private function isFlow(string $frame): bool
    {
        return substr($frame, 8, 3) === "\x00\x53\x13";
    }

    private function isDetach(string $frame): bool
    {
        return substr($frame, 8, 3) === "\x00\x53\x16";
    }

    private function failIfRemoteSessionEnded(string $frame): void
    {
        if (substr($frame, 8, 3) !== "\x00\x53\x17") {
            return;
        }

        $this->engine->push($frame);

        throw ClientException::remoteSessionEnded();
    }
}
