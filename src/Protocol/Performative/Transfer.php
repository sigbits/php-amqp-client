<?php

declare(strict_types=1);

namespace Sigbits\Amqp\Protocol\Performative;

use InvalidArgumentException;
use Sigbits\Amqp\Protocol\Type\UInt;

final readonly class Transfer
{
    public function __construct(
        public int $handle,
        public int $deliveryId,
        public string $deliveryTag,
        public int $messageFormat,
        public bool $more,
    ) {
        new UInt($handle);
        new UInt($deliveryId);
        new UInt($messageFormat);

        if ($deliveryTag === '') {
            throw new InvalidArgumentException('AMQP transfer delivery-tag must not be empty.');
        }

        if (strlen($deliveryTag) > 255) {
            throw new InvalidArgumentException('AMQP transfer delivery-tag must fit in vbin8 encoding.');
        }
    }
}
