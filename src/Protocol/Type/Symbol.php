<?php

declare(strict_types=1);

namespace Sigbits\Amqp\Protocol\Type;

final readonly class Symbol
{
    public function __construct(
        public string $value,
    ) {
    }
}
