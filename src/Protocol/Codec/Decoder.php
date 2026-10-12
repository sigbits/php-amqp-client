<?php

declare(strict_types=1);

namespace Sigbits\Amqp\Protocol\Codec;

use Sigbits\Amqp\Protocol\Type\Binary;
use Sigbits\Amqp\Protocol\Type\Byte;
use Sigbits\Amqp\Protocol\Type\Char;
use Sigbits\Amqp\Protocol\Type\Decimal128;
use Sigbits\Amqp\Protocol\Type\Decimal32;
use Sigbits\Amqp\Protocol\Type\Decimal64;
use Sigbits\Amqp\Protocol\Type\Float32;
use Sigbits\Amqp\Protocol\Type\Float64;
use Sigbits\Amqp\Protocol\Type\Int_;
use Sigbits\Amqp\Protocol\Type\Long_;
use Sigbits\Amqp\Protocol\Type\Short;
use Sigbits\Amqp\Protocol\Type\Symbol;
use Sigbits\Amqp\Protocol\Type\Timestamp;
use Sigbits\Amqp\Protocol\Type\UByte;
use Sigbits\Amqp\Protocol\Type\UInt;
use Sigbits\Amqp\Protocol\Type\ULong;
use Sigbits\Amqp\Protocol\Type\UShort;
use Sigbits\Amqp\Protocol\Type\Uuid;

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
            0x72 => $this->decodeFloat32($bytes),
            0x73 => $this->decodeChar($bytes),
            0x74 => $this->decodeDecimal32($bytes),
            0x80 => $this->decodeULong($bytes),
            0x81 => $this->decodeLong($bytes),
            0x82 => $this->decodeFloat64($bytes),
            0x83 => $this->decodeTimestamp($bytes),
            0x84 => $this->decodeDecimal64($bytes),
            0x94 => $this->decodeDecimal128($bytes),
            0x98 => $this->decodeUuid($bytes),
            0xa0 => $this->decodeBinary8($bytes),
            0xa1 => $this->decodeString8($bytes),
            0xa3 => $this->decodeSymbol8($bytes),
            0xb0 => $this->decodeBinary32($bytes),
            0xb1 => $this->decodeString32($bytes),
            0xb3 => $this->decodeSymbol32($bytes),
            0x44 => new ULong(0),
            0x53 => $this->decodeSmallULong($bytes),
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

        return new Long_($this->signedInt64BytesToInt(substr($bytes, 1, 8)));
    }

    private function decodeSmallULong(string $bytes): ULong
    {
        if (strlen($bytes) < 2) {
            throw DecodeException::truncatedULong();
        }

        return new ULong(ord($bytes[1]));
    }

    private function decodeULong(string $bytes): ULong
    {
        if (strlen($bytes) < 9) {
            throw DecodeException::truncatedULong();
        }

        return new ULong($this->unsignedInt64BytesToDecimal(substr($bytes, 1, 8)));
    }

    private function decodeChar(string $bytes): Char
    {
        if (strlen($bytes) < 5) {
            throw DecodeException::truncatedChar();
        }

        $codePoint = (ord($bytes[1]) << 24)
            | (ord($bytes[2]) << 16)
            | (ord($bytes[3]) << 8)
            | ord($bytes[4]);

        try {
            return new Char($codePoint);
        } catch (\InvalidArgumentException) {
            throw DecodeException::invalidChar();
        }
    }

    private function decodeDecimal32(string $bytes): Decimal32
    {
        if (strlen($bytes) < 5) {
            throw DecodeException::truncatedDecimal32();
        }

        return new Decimal32(substr($bytes, 1, 4));
    }

    private function decodeFloat32(string $bytes): Float32
    {
        if (strlen($bytes) < 5) {
            throw DecodeException::truncatedFloat();
        }

        /** @var array{1: float} $unpacked */
        $unpacked = unpack('G', substr($bytes, 1, 4));

        return new Float32($unpacked[1]);
    }

    private function decodeDecimal64(string $bytes): Decimal64
    {
        if (strlen($bytes) < 9) {
            throw DecodeException::truncatedDecimal64();
        }

        return new Decimal64(substr($bytes, 1, 8));
    }

    private function decodeFloat64(string $bytes): Float64
    {
        if (strlen($bytes) < 9) {
            throw DecodeException::truncatedDouble();
        }

        /** @var array{1: float} $unpacked */
        $unpacked = unpack('E', substr($bytes, 1, 8));

        return new Float64($unpacked[1]);
    }

    private function decodeDecimal128(string $bytes): Decimal128
    {
        if (strlen($bytes) < 17) {
            throw DecodeException::truncatedDecimal128();
        }

        return new Decimal128(substr($bytes, 1, 16));
    }

    private function decodeTimestamp(string $bytes): Timestamp
    {
        if (strlen($bytes) < 9) {
            throw DecodeException::truncatedTimestamp();
        }

        return new Timestamp($this->signedInt64BytesToInt(substr($bytes, 1, 8)));
    }

    private function decodeUuid(string $bytes): Uuid
    {
        if (strlen($bytes) < 17) {
            throw DecodeException::truncatedUuid();
        }

        $hex = bin2hex(substr($bytes, 1, 16));

        return new Uuid(sprintf(
            '%s-%s-%s-%s-%s',
            substr($hex, 0, 8),
            substr($hex, 8, 4),
            substr($hex, 12, 4),
            substr($hex, 16, 4),
            substr($hex, 20, 12),
        ));
    }

    private function signedInt64BytesToInt(string $bytes): int
    {
        $high = (ord($bytes[0]) << 24)
            | (ord($bytes[1]) << 16)
            | (ord($bytes[2]) << 8)
            | ord($bytes[3]);
        $low = (ord($bytes[4]) << 24)
            | (ord($bytes[5]) << 16)
            | (ord($bytes[6]) << 8)
            | ord($bytes[7]);

        if ($high >= 2147483648) {
            $high -= 4294967296;
        }

        return ($high << 32) | $low;
    }

    private function decodeBinary8(string $bytes): Binary
    {
        return new Binary($this->decodeVariable8($bytes, DecodeException::truncatedBinary(...)));
    }

    private function decodeBinary32(string $bytes): Binary
    {
        return new Binary($this->decodeVariable32($bytes, DecodeException::truncatedBinary(...)));
    }

    private function decodeString8(string $bytes): string
    {
        return $this->decodeVariable8($bytes, DecodeException::truncatedString(...));
    }

    private function decodeString32(string $bytes): string
    {
        return $this->decodeVariable32($bytes, DecodeException::truncatedString(...));
    }

    private function decodeSymbol8(string $bytes): Symbol
    {
        return new Symbol($this->decodeVariable8($bytes, DecodeException::truncatedSymbol(...)));
    }

    private function decodeSymbol32(string $bytes): Symbol
    {
        return new Symbol($this->decodeVariable32($bytes, DecodeException::truncatedSymbol(...)));
    }

    /**
     * @param callable(): DecodeException $truncated
     */
    private function decodeVariable8(string $bytes, callable $truncated): string
    {
        if (strlen($bytes) < 2) {
            throw $truncated();
        }

        $length = ord($bytes[1]);

        if (strlen($bytes) < 2 + $length) {
            throw $truncated();
        }

        return substr($bytes, 2, $length);
    }

    /**
     * @param callable(): DecodeException $truncated
     */
    private function decodeVariable32(string $bytes, callable $truncated): string
    {
        if (strlen($bytes) < 5) {
            throw $truncated();
        }

        $length = (ord($bytes[1]) << 24)
            | (ord($bytes[2]) << 16)
            | (ord($bytes[3]) << 8)
            | ord($bytes[4]);

        if (strlen($bytes) < 5 + $length) {
            throw $truncated();
        }

        return substr($bytes, 5, $length);
    }

    private function unsignedInt64BytesToDecimal(string $bytes): string
    {
        $decimal = '0';

        for ($index = 0; $index < 8; ++$index) {
            $decimal = $this->decimalMultiplyByInt($decimal, 256);
            $decimal = $this->decimalAddInt($decimal, ord($bytes[$index]));
        }

        return $decimal;
    }

    private function decimalMultiplyByInt(string $value, int $multiplier): string
    {
        $carry = 0;
        $result = '';

        for ($index = strlen($value) - 1; $index >= 0; --$index) {
            $product = ((int) $value[$index] * $multiplier) + $carry;
            $result = (string) ($product % 10) . $result;
            $carry = intdiv($product, 10);
        }

        while ($carry > 0) {
            $result = (string) ($carry % 10) . $result;
            $carry = intdiv($carry, 10);
        }

        $result = ltrim($result, '0');

        return $result === '' ? '0' : $result;
    }

    private function decimalAddInt(string $value, int $addend): string
    {
        $carry = $addend;
        $result = '';

        for ($index = strlen($value) - 1; $index >= 0; --$index) {
            $sum = (int) $value[$index] + ($carry % 10);
            $carry = intdiv($carry, 10) + intdiv($sum, 10);
            $result = (string) ($sum % 10) . $result;
        }

        while ($carry > 0) {
            $result = (string) ($carry % 10) . $result;
            $carry = intdiv($carry, 10);
        }

        $result = ltrim($result, '0');

        return $result === '' ? '0' : $result;
    }
}
