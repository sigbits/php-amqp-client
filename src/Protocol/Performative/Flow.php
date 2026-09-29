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
        public ?int $incomingWindow = null,
        public ?int $nextOutgoingId = null,
        public ?int $outgoingWindow = null,
    ) {
        new UInt($handle);
        new UInt($deliveryCount);
        new UInt($linkCredit);

        if ($incomingWindow !== null) {
            new UInt($incomingWindow);
        }

        if ($nextOutgoingId !== null) {
            new UInt($nextOutgoingId);
        }

        if ($outgoingWindow !== null) {
            new UInt($outgoingWindow);
        }
    }
}
