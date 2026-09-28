<?php

declare(strict_types=1);

namespace Sigbits\Amqp\Client;

use Closure;
use Sigbits\Amqp\Engine\ReceiverLinkEngine;
use Sigbits\Amqp\Protocol\Message\Message;

final class Receiver
{
    /**
     * @param callable(int): ?string $read
     */
    public function __construct(
        private readonly ReceiverLinkEngine $link,
        callable $read,
    ) {
        $this->read = $read(...);
    }

    /**
     * @var Closure(int): ?string
     */
    private readonly Closure $read;

    public function receive(int $timeoutMilliseconds): ?Message
    {
        $message = $this->link->receive();

        if ($message !== null) {
            return $message;
        }

        $deadline = microtime(true) + ($timeoutMilliseconds / 1000);

        do {
            $remainingMilliseconds = max(0, (int) ceil(($deadline - microtime(true)) * 1000));
            $bytes = ($this->read)($remainingMilliseconds);

            if ($bytes !== null && $bytes !== '') {
                $this->link->push($bytes);
                $message = $this->link->receive();

                if ($message !== null) {
                    return $message;
                }
            }
        } while (microtime(true) < $deadline);

        return null;
    }
}
