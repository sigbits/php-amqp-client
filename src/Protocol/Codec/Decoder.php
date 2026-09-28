<?php

declare(strict_types=1);

namespace Sigbits\Amqp\Protocol\Codec;

final class Decoder
{
    public function decode(string $bytes): mixed
    {
        $formatCode = ord($bytes[0]);

        return match ($formatCode) {
            0x40 => null,
            0x41 => true,
            0x42 => false,
            0x56 => $this->decodeBoolean($bytes),
            default => throw DecodeException::unsupportedFormatCode($formatCode),
        };
    }

    private function decodeBoolean(string $bytes): bool
    {
        if (strlen($bytes) < 2) {
            throw DecodeException::truncatedBoolean();
        }

        return ord($bytes[1]) !== 0;
    }
}
