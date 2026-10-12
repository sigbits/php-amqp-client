<?php

declare(strict_types=1);

namespace Sigbits\Amqp\Protocol\Type;

final readonly class Binary
{
    public function __construct(
        public string $value,
    ) {
    }
}
