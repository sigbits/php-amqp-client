<?php

declare(strict_types=1);

namespace Sigbits\Amqp\Protocol\Codec;

use Sigbits\Amqp\Protocol\Type\UByte;
use Sigbits\Amqp\Protocol\Type\UInt;
use Sigbits\Amqp\Protocol\Type\UShort;

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

        if ($value instanceof UByte) {
            return "\x50" . chr($value->value);
        }

        if ($value instanceof UShort) {
            return "\x60" . pack('n', $value->value);
        }

        if ($value instanceof UInt) {
            return "\x70" . pack('N', $value->value);
        }

        throw EncodeException::unsupportedValue($value);
    }
}
