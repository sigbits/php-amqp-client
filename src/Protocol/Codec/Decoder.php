<?php

declare(strict_types=1);

namespace Sigbits\Amqp\Protocol\Codec;

use Sigbits\Amqp\Protocol\Type\UByte;
use Sigbits\Amqp\Protocol\Type\UInt;
use Sigbits\Amqp\Protocol\Type\UShort;

final class Decoder
{
    public function decode(string $bytes): mixed
    {
        $formatCode = ord($bytes[0]);

        return match ($formatCode) {
            0x40 => null,
            0x41 => true,
            0x42 => false,
            0x50 => $this->decodeUByte($bytes),
            0x56 => $this->decodeBoolean($bytes),
            0x60 => $this->decodeUShort($bytes),
            0x70 => $this->decodeUInt($bytes),
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

    private function decodeUByte(string $bytes): UByte
    {
        if (strlen($bytes) < 2) {
            throw DecodeException::truncatedUByte();
        }

        return new UByte(ord($bytes[1]));
    }

    private function decodeUShort(string $bytes): UShort
    {
        if (strlen($bytes) < 3) {
            throw DecodeException::truncatedUShort();
        }

        return new UShort((ord($bytes[1]) << 8) | ord($bytes[2]));
    }

    private function decodeUInt(string $bytes): UInt
    {
        if (strlen($bytes) < 5) {
            throw DecodeException::truncatedUInt();
        }

        return new UInt(
            (ord($bytes[1]) << 24)
            | (ord($bytes[2]) << 16)
            | (ord($bytes[3]) << 8)
            | ord($bytes[4]),
        );
    }
}
