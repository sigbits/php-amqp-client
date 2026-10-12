<?php

declare(strict_types=1);

namespace Sigbits\Amqp\Protocol\Performative;

use InvalidArgumentException;
use Sigbits\Amqp\Protocol\Type\UInt;

final readonly class Target
{
    public function __construct(
        public ?string $address = null,
        public ?int $durable = null,
        public ?string $expiryPolicy = null,
        public ?int $timeout = null,
        public ?bool $dynamic = null,
    ) {
        if ($address === '') {
            throw new InvalidArgumentException('AMQP target address must not be empty.');
        }

        if ($address !== null && strlen($address) > 255) {
            throw new InvalidArgumentException('AMQP target address must fit in str8 encoding.');
        }

        if ($durable !== null) {
            new UInt($durable);
        }

        if ($expiryPolicy === '') {
            throw new InvalidArgumentException('AMQP target expiry-policy must not be empty.');
        }

        if ($timeout !== null) {
            new UInt($timeout);
        }
    }
}
