<?php

declare(strict_types=1);

namespace Sigbits\Amqp\Protocol\Type;

use InvalidArgumentException;

final readonly class Decimal128
{
    public string $bytes;

    public function __construct(string $bytes)
    {
        if (strlen($bytes) !== 16) {
            throw new InvalidArgumentException('AMQP decimal128 value must contain exactly 16 bytes.');
        }

        $this->bytes = $bytes;
    }
}
