<?php

declare(strict_types=1);

namespace Sigbits\Amqp\Client\Internal;

use Closure;
use Sigbits\Amqp\Client\Delivery;
use Sigbits\Amqp\Client\Receiver;
use Sigbits\Amqp\Client\Sender;
use Sigbits\Amqp\Client\Session;
use Sigbits\Amqp\Engine\ReceiverLinkEngine;
use Sigbits\Amqp\Engine\SenderLinkEngine;
use Sigbits\Amqp\Engine\SessionEngine;
use Sigbits\Amqp\Protocol\Message\Message;

/**
 * @internal
 */
final class ClientObjectFactory
{
    /**
     * @param callable(list<string>): void $writeAll
     * @param callable(): string $readFrame
     */
    public static function session(
        SessionEngine $engine,
        int $channel,
        callable $writeAll,
        callable $readFrame,
    ): Session {
        $factory = Closure::bind(
            static fn (
                SessionEngine $engine,
                int $channel,
                callable $writeAll,
                callable $readFrame,
            ): Session => new Session($engine, $channel, $writeAll, $readFrame),
            null,
            Session::class,
        );

        /** @var Closure(SessionEngine, int, callable, callable): Session $factory */
        return $factory($engine, $channel, $writeAll, $readFrame);
    }

    /**
     * @param callable(list<string>): void $writeAll
     * @param callable(): string $readFrame
     */
    public static function sender(
        SenderLinkEngine $engine,
        callable $writeAll,
        callable $readFrame,
    ): Sender {
        $factory = Closure::bind(
            static fn (
                SenderLinkEngine $engine,
                callable $writeAll,
                callable $readFrame,
            ): Sender => new Sender($engine, $writeAll, $readFrame),
            null,
            Sender::class,
        );

        /** @var Closure(SenderLinkEngine, callable, callable): Sender $factory */
        return $factory($engine, $writeAll, $readFrame);
    }

    /**
     * @param callable(int): ?string $read
     * @param null|callable(list<string>): void $writeAll
     */
    public static function receiver(
        ReceiverLinkEngine $link,
        callable $read,
        ?callable $writeAll = null,
    ): Receiver {
        $factory = Closure::bind(
            static fn (
                ReceiverLinkEngine $link,
                callable $read,
                ?callable $writeAll = null,
            ): Receiver => new Receiver($link, $read, $writeAll),
            null,
            Receiver::class,
        );

        /** @var Closure(ReceiverLinkEngine, callable, ?callable): Receiver $factory */
        return $factory($link, $read, $writeAll);
    }

    /**
     * @param callable(int): void $accept
     * @param callable(int): void $release
     * @param callable(int): void $reject
     */
    public static function delivery(
        int $deliveryId,
        Message $message,
        callable $accept,
        callable $release,
        callable $reject,
    ): Delivery {
        $factory = Closure::bind(
            static fn (
                int $deliveryId,
                Message $message,
                callable $accept,
                callable $release,
                callable $reject,
            ): Delivery => new Delivery($deliveryId, $message, $accept, $release, $reject),
            null,
            Delivery::class,
        );

        /** @var Closure(int, Message, callable, callable, callable): Delivery $factory */
        return $factory($deliveryId, $message, $accept, $release, $reject);
    }
}
