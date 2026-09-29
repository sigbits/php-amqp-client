<?php

declare(strict_types=1);

namespace Sigbits\Amqp\Engine;

use Sigbits\Amqp\Protocol\Message\Message;

final readonly class ReceivedDelivery
{
    public function __construct(
        public int $deliveryId,
        public Message $message,
    ) {
    }
}
