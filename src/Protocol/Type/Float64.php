<?php

declare(strict_types=1);

namespace Sigbits\Amqp\Protocol\Type;

final readonly class Float64
{
    public function __construct(
        public float $value,
    ) {
    }
}
