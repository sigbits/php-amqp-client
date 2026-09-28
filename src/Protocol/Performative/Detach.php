<?php

declare(strict_types=1);

namespace Sigbits\Amqp\Protocol\Performative;

use Sigbits\Amqp\Protocol\Type\UInt;

final readonly class Detach
{
    public function __construct(
        public int $handle,
    ) {
        new UInt($handle);
    }
}
