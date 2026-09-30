<?php

declare(strict_types=1);

namespace Sigbits\Amqp\Client;

use Closure;
use Sigbits\Amqp\Protocol\Message\Message;

final class Delivery
{
    private bool $settled = false;

    /**
     * @param callable(int): void $accept
     * @param callable(int): void $release
     * @param callable(int): void $reject
     */
    public function __construct(
        private readonly int $deliveryId,
        private readonly Message $message,
        callable $accept,
        callable $release,
        callable $reject,
    ) {
        $this->accept = $accept(...);
        $this->release = $release(...);
        $this->reject = $reject(...);
    }

    /**
     * @var Closure(int): void
     */
    private readonly Closure $accept;

    /**
     * @var Closure(int): void
     */
    private readonly Closure $release;

    /**
     * @var Closure(int): void
     */
    private readonly Closure $reject;

    public function message(): Message
    {
        return $this->message;
    }

    public function accept(): void
    {
        $this->settle();

        ($this->accept)($this->deliveryId);
    }

    public function release(): void
    {
        $this->settle();

        ($this->release)($this->deliveryId);
    }

    public function reject(): void
    {
        $this->settle();

        ($this->reject)($this->deliveryId);
    }

    private function settle(): void
    {
        if ($this->settled) {
            throw ClientException::deliveryAlreadySettled();
        }

        $this->settled = true;
    }
}
