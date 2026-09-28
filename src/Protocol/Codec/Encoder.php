<?php

declare(strict_types=1);

namespace Sigbits\Amqp\Protocol\Codec;

final class Encoder
{
    public function encode(mixed $value): string
    {
        if ($value === null) {
            return "\x40";
        }

        if ($value === true) {
            return "\x41";
        }

        if ($value === false) {
            return "\x42";
        }

        throw EncodeException::unsupportedValue($value);
    }
}
