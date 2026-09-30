<?php

declare(strict_types=1);

namespace Sigbits\Amqp\Client;

use Closure;
use Sigbits\Amqp\Client\Internal\ClientObjectFactory;
use Sigbits\Amqp\Engine\ReceivedDelivery;
use Sigbits\Amqp\Engine\ReceiverLinkEngine;
use Sigbits\Amqp\Engine\ReceiverLinkState;
use Sigbits\Amqp\Protocol\Message\Message;

final class Receiver
{
    private int $receivedDeliveryCount = 0;

    /**
     * @param callable(int): ?string $read
     * @param null|callable(list<string>): void $writeAll
     */
    private function __construct(
        private readonly ReceiverLinkEngine $link,
        callable $read,
        ?callable $writeAll = null,
    ) {
        $this->read = $read(...);
        $this->writeAll = $writeAll === null ? null : $writeAll(...);
    }

    /**
     * @var Closure(int): ?string
     */
    private readonly Closure $read;

    /**
     * @var null|Closure(list<string>): void
     */
    private readonly ?Closure $writeAll;

    public function state(): ReceiverLinkState
    {
        return $this->link->state();
    }

    public function receive(int $timeoutMilliseconds): ?Message
    {
        return $this->receiveDelivery($timeoutMilliseconds)?->message();
    }

    public function receiveDelivery(int $timeoutMilliseconds): ?Delivery
    {
        if ($this->link->state() === ReceiverLinkState::Detached) {
            throw ClientException::receiverLinkDetached();
        }

        $delivery = $this->link->receiveDelivery();

        if ($delivery !== null) {
            return $this->delivery($delivery);
        }

        $deadline = microtime(true) + ($timeoutMilliseconds / 1000);

        do {
            $remainingMilliseconds = max(0, (int) ceil(($deadline - microtime(true)) * 1000));
            $bytes = ($this->read)($remainingMilliseconds);

            if ($bytes !== null && $bytes !== '' && $this->isReceiverLinkFrame($bytes)) {
                $this->link->push($bytes);

                if ($this->link->state() === ReceiverLinkState::Detached) {
                    throw ClientException::receiverLinkDetached();
                }

                $delivery = $this->link->receiveDelivery();

                if ($delivery !== null) {
                    return $this->delivery($delivery);
                }
            }
        } while (microtime(true) < $deadline);

        return null;
    }

    public function grantCredit(int $credit): void
    {
        if ($this->link->state() === ReceiverLinkState::Detached) {
            throw ClientException::receiverLinkDetached();
        }

        if ($this->writeAll === null) {
            return;
        }

        ($this->writeAll)($this->link->grantCredit(
            deliveryCount: $this->receivedDeliveryCount,
            linkCredit: $credit,
        ));
    }

    public function detach(): void
    {
        if ($this->link->state() === ReceiverLinkState::Detached) {
            return;
        }

        if ($this->writeAll === null) {
            return;
        }

        ($this->writeAll)($this->link->detach());

        while ($this->link->state() !== ReceiverLinkState::Detached) {
            $bytes = ($this->read)(0);

            if ($bytes !== null && $bytes !== '') {
                $this->link->push($bytes);
            }
        }
    }

    private function isReceiverLinkFrame(string $frame): bool
    {
        $descriptor = substr($frame, 8, 3);

        return $descriptor === "\x00\x53\x14" || $descriptor === "\x00\x53\x16";
    }

    private function delivery(ReceivedDelivery $delivery): Delivery
    {
        ++$this->receivedDeliveryCount;

        return ClientObjectFactory::delivery(
            deliveryId: $delivery->deliveryId,
            message: $delivery->message,
            accept: $this->settle($this->link->accept(...)),
            release: $this->settle($this->link->release(...)),
            reject: $this->settle($this->link->reject(...)),
        );
    }

    /**
     * @param callable(int): list<string> $settle
     *
     * @return callable(int): void
     */
    private function settle(callable $settle): callable
    {
        return function (int $deliveryId) use ($settle): void {
            if ($this->link->state() === ReceiverLinkState::Detached) {
                throw ClientException::receiverLinkDetachedDuringSettlement();
            }

            if ($this->writeAll === null) {
                return;
            }

            ($this->writeAll)($settle($deliveryId));
        };
    }
}
