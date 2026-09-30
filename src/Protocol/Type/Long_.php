<?php

declare(strict_types=1);

namespace Sigbits\Amqp\Protocol\Type;

final readonly class Long_
{
    public function __construct(
        public int $value,
    ) {
    }
}
