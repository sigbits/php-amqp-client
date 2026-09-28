<?php

declare(strict_types=1);

namespace Sigbits\Amqp\Protocol\Codec;

final class Decoder
{
    public function decode(string $bytes): mixed
    {
        $formatCode = ord($bytes[0]);

        if ($formatCode !== 0x40) {
            throw DecodeException::unsupportedFormatCode($formatCode);
        }

        return null;
    }
}
