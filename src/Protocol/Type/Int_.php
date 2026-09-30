<?php

declare(strict_types=1);

namespace Sigbits\Amqp\Protocol\Type;

use InvalidArgumentException;

final readonly class Int_
{
    public function __construct(
        public int $value,
    ) {
        if ($value < -2147483648 || $value > 2147483647) {
            throw new InvalidArgumentException('AMQP int value must be between -2147483648 and 2147483647.');
        }
    }
}
