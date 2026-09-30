<?php

declare(strict_types=1);

namespace Sigbits\Amqp\Protocol\Codec;

use Sigbits\Amqp\Protocol\Type\Byte;
use Sigbits\Amqp\Protocol\Type\Int_;
use Sigbits\Amqp\Protocol\Type\Long_;
use Sigbits\Amqp\Protocol\Type\Short;
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

        if ($value instanceof Byte) {
            return "\x51" . chr($value->value & 0xff);
        }

        if ($value instanceof UShort) {
            return "\x60" . pack('n', $value->value);
        }

        if ($value instanceof Short) {
            return "\x61" . pack('n', $value->value & 0xffff);
        }

        if ($value instanceof UInt) {
            return "\x70" . pack('N', $value->value);
        }

        if ($value instanceof Int_) {
            return "\x71" . pack('N', $value->value & 0xffffffff);
        }

        if ($value instanceof Long_) {
            return "\x81" . $this->encodeSignedInt64($value->value);
        }

        throw EncodeException::unsupportedValue($value);
    }

    private function encodeSignedInt64(int $value): string
    {
        return pack(
            'NN',
            ($value >> 32) & 0xffffffff,
            $value & 0xffffffff,
        );
    }
}
