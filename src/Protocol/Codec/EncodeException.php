<?php

declare(strict_types=1);

namespace Sigbits\Amqp\Protocol\Codec;

use RuntimeException;

final class EncodeException extends RuntimeException
{
    public static function unsupportedValue(mixed $value): self
    {
        return new self(sprintf(
            'Cannot encode PHP value of type %s as AMQP value.',
            get_debug_type($value),
        ));
    }
}
