<?php

declare(strict_types=1);

namespace Sigbits\Amqp\Protocol\Type;

use InvalidArgumentException;

final readonly class UByte
{
    public function __construct(
        public int $value,
    ) {
        if ($value < 0 || $value > 255) {
            throw new InvalidArgumentException('AMQP ubyte value must be between 0 and 255.');
        }
    }
}
