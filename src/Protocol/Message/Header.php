<?php

declare(strict_types=1);

namespace Sigbits\Amqp\Protocol\Message;

use InvalidArgumentException;

final readonly class Header
{
    public function __construct(
        public ?bool $durable = null,
        public ?int $priority = null,
        public ?int $ttl = null,
        public ?bool $firstAcquirer = null,
        public ?int $deliveryCount = null,
    ) {
        if ($priority !== null && ($priority < 0 || $priority > 255)) {
            throw new InvalidArgumentException('AMQP message priority must fit in ubyte encoding.');
        }

        if ($ttl !== null && ($ttl < 0 || $ttl > 4294967295)) {
            throw new InvalidArgumentException('AMQP message TTL must fit in uint encoding.');
        }

        if ($deliveryCount !== null && ($deliveryCount < 0 || $deliveryCount > 4294967295)) {
            throw new InvalidArgumentException('AMQP message delivery count must fit in uint encoding.');
        }
    }
}
