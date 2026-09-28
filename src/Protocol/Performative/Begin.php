<?php

declare(strict_types=1);

namespace Sigbits\Amqp\Protocol\Performative;

use Sigbits\Amqp\Protocol\Type\UInt;
use Sigbits\Amqp\Protocol\Type\UShort;

final readonly class Begin
{
    public function __construct(
        public ?int $remoteChannel,
        public int $nextOutgoingId,
        public int $incomingWindow,
        public int $outgoingWindow,
    ) {
        if ($remoteChannel !== null) {
            new UShort($remoteChannel);
        }

        new UInt($nextOutgoingId);
        new UInt($incomingWindow);
        new UInt($outgoingWindow);
    }
}
