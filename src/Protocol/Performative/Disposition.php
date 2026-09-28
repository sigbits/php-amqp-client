<?php

declare(strict_types=1);

namespace Sigbits\Amqp\Protocol\Performative;

use Sigbits\Amqp\Protocol\Type\UInt;

final readonly class Disposition
{
    public function __construct(
        public int $deliveryId,
        public SettlementOutcome $outcome,
    ) {
        new UInt($deliveryId);
    }
}
