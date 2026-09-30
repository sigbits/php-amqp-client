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

final class Decoder
{
    public function decode(string $bytes): mixed
    {
        $formatCode = ord($bytes[0]);

        return match ($formatCode) {
            0x40 => null,
            0x41 => true,
            0x42 => false,
            0x43 => new UInt(0),
            0x50 => $this->decodeUByte($bytes),
            0x51 => $this->decodeByte($bytes),
            0x52 => $this->decodeSmallUInt($bytes),
            0x56 => $this->decodeBoolean($bytes),
            0x60 => $this->decodeUShort($bytes),
            0x61 => $this->decodeShort($bytes),
            0x70 => $this->decodeUInt($bytes),
            0x71 => $this->decodeInt($bytes),
            0x81 => $this->decodeLong($bytes),
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

    private function decodeByte(string $bytes): Byte
    {
        if (strlen($bytes) < 2) {
            throw DecodeException::truncatedByte();
        }

        $unsigned = ord($bytes[1]);

        return new Byte($unsigned >= 128 ? $unsigned - 256 : $unsigned);
    }

    private function decodeUShort(string $bytes): UShort
    {
        if (strlen($bytes) < 3) {
            throw DecodeException::truncatedUShort();
        }

        return new UShort((ord($bytes[1]) << 8) | ord($bytes[2]));
    }

    private function decodeShort(string $bytes): Short
    {
        if (strlen($bytes) < 3) {
            throw DecodeException::truncatedShort();
        }

        $unsigned = (ord($bytes[1]) << 8) | ord($bytes[2]);

        return new Short($unsigned >= 32768 ? $unsigned - 65536 : $unsigned);
    }

    private function decodeSmallUInt(string $bytes): UInt
    {
        if (strlen($bytes) < 2) {
            throw DecodeException::truncatedUInt();
        }

        return new UInt(ord($bytes[1]));
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

    private function decodeInt(string $bytes): Int_
    {
        if (strlen($bytes) < 5) {
            throw DecodeException::truncatedInt();
        }

        $unsigned = (ord($bytes[1]) << 24)
            | (ord($bytes[2]) << 16)
            | (ord($bytes[3]) << 8)
            | ord($bytes[4]);

        if ($unsigned >= 2147483648) {
            return new Int_($unsigned - 4294967296);
        }

        return new Int_($unsigned);
    }

    private function decodeLong(string $bytes): Long_
    {
        if (strlen($bytes) < 9) {
            throw DecodeException::truncatedLong();
        }

        $high = (ord($bytes[1]) << 24)
            | (ord($bytes[2]) << 16)
            | (ord($bytes[3]) << 8)
            | ord($bytes[4]);
        $low = (ord($bytes[5]) << 24)
            | (ord($bytes[6]) << 16)
            | (ord($bytes[7]) << 8)
            | ord($bytes[8]);

        if ($high >= 2147483648) {
            $high -= 4294967296;
        }

        return new Long_(($high << 32) | $low);
    }
}
