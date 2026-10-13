<?php

declare(strict_types=1);

namespace Sigbits\Amqp\Protocol\Performative;

use Sigbits\Amqp\Protocol\Type\AmqpMap;

final readonly class Modified implements DeliveryState
{
    public function __construct(
        public ?bool $deliveryFailed = null,
        public ?bool $undeliverableHere = null,
        public ?AmqpMap $messageAnnotations = null,
    ) {
    }
}
