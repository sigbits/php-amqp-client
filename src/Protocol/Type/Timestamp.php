<?php

declare(strict_types=1);

namespace Sigbits\Amqp\Protocol\Type;

final readonly class Timestamp
{
    public function __construct(
        public int $milliseconds,
    ) {
    }
}
