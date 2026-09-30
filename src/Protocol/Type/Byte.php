<?php

declare(strict_types=1);

namespace Sigbits\Amqp\Protocol\Type;

use InvalidArgumentException;

final readonly class Byte
{
    public function __construct(
        public int $value,
    ) {
        if ($value < -128 || $value > 127) {
            throw new InvalidArgumentException('AMQP byte value must be between -128 and 127.');
        }
    }
}
