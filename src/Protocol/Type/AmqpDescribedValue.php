<?php

declare(strict_types=1);

namespace Sigbits\Amqp\Protocol\Type;

use InvalidArgumentException;

final readonly class AmqpDescribedValue
{
    public ULong|Symbol $descriptor;

    public mixed $value;

    public function __construct(
        mixed $descriptor,
        mixed $value,
    ) {
        if (!$descriptor instanceof ULong && !$descriptor instanceof Symbol) {
            throw new InvalidArgumentException('AMQP described value descriptor must be an ulong or symbol.');
        }

        $this->descriptor = $descriptor;
        $this->value = $value;
    }
}
