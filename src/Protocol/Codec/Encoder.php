<?php

declare(strict_types=1);

namespace Sigbits\Amqp\Protocol\Codec;

final class Encoder
{
    public function encode(mixed $value): string
    {
        if ($value !== null) {
            throw EncodeException::unsupportedValue($value);
        }

        return "\x40";
    }
}
