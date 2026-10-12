<?php

declare(strict_types=1);

namespace Sigbits\Amqp\Protocol\Performative;

use InvalidArgumentException;

final readonly class Source
{
    public function __construct(
        public ?string $address = null,
    ) {
        if ($address === '') {
            throw new InvalidArgumentException('AMQP source address must not be empty.');
        }

        if ($address !== null && strlen($address) > 255) {
            throw new InvalidArgumentException('AMQP source address must fit in str8 encoding.');
        }
    }
}
