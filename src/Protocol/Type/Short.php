<?php

declare(strict_types=1);

namespace Sigbits\Amqp\Protocol\Type;

use InvalidArgumentException;

final readonly class Short
{
    public function __construct(
        public int $value,
    ) {
        if ($value < -32768 || $value > 32767) {
            throw new InvalidArgumentException('AMQP short value must be between -32768 and 32767.');
        }
    }
}
