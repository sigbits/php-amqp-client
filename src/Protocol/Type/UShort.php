<?php

declare(strict_types=1);

namespace Sigbits\Amqp\Protocol\Type;

use InvalidArgumentException;

final readonly class UShort
{
    public function __construct(
        public int $value,
    ) {
        if ($value < 0 || $value > 65535) {
            throw new InvalidArgumentException('AMQP ushort value must be between 0 and 65535.');
        }
    }
}
