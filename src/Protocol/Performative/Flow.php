<?php

declare(strict_types=1);

namespace Sigbits\Amqp\Protocol\Performative;

use Sigbits\Amqp\Protocol\Type\UInt;

final readonly class Flow
{
    public function __construct(
        public int $handle,
        public int $deliveryCount,
        public int $linkCredit,
    ) {
        new UInt($handle);
        new UInt($deliveryCount);
        new UInt($linkCredit);
    }
}
