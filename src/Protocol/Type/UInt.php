<?php

declare(strict_types=1);

namespace Sigbits\Amqp\Protocol\Type;

use InvalidArgumentException;

final readonly class UInt
{
    public function __construct(
        public int $value,
    ) {
        if ($value < 0 || $value > 4294967295) {
            throw new InvalidArgumentException('AMQP uint value must be between 0 and 4294967295.');
        }
    }
}
