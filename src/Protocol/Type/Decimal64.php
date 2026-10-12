<?php

declare(strict_types=1);

namespace Sigbits\Amqp\Protocol\Type;

use InvalidArgumentException;

final readonly class Decimal64
{
    public string $bytes;

    public function __construct(string $bytes)
    {
        if (strlen($bytes) !== 8) {
            throw new InvalidArgumentException('AMQP decimal64 value must contain exactly 8 bytes.');
        }

        $this->bytes = $bytes;
    }
}
