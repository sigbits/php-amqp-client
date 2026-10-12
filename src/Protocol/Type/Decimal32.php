<?php

declare(strict_types=1);

namespace Sigbits\Amqp\Protocol\Type;

use InvalidArgumentException;

final readonly class Decimal32
{
    public string $bytes;

    public function __construct(string $bytes)
    {
        if (strlen($bytes) !== 4) {
            throw new InvalidArgumentException('AMQP decimal32 value must contain exactly 4 bytes.');
        }

        $this->bytes = $bytes;
    }
}
