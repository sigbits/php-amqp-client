<?php

declare(strict_types=1);

namespace Sigbits\Amqp\Protocol\Codec;

use LogicException;
use Sigbits\Amqp\Protocol\Type\AmqpList;
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

        if ($value instanceof ULong) {
            return "\x80" . $this->encodeUnsignedInt64($value->value);
        }

        if ($value instanceof Char) {
            return "\x73" . pack('N', $value->codePoint);
        }

        if ($value instanceof Decimal32) {
            return "\x74" . $value->bytes;
        }

        if ($value instanceof Float32) {
            return "\x72" . pack('G', $value->value);
        }

        if ($value instanceof Decimal64) {
            return "\x84" . $value->bytes;
        }

        if ($value instanceof Decimal128) {
            return "\x94" . $value->bytes;
        }

        if ($value instanceof Float64) {
            return "\x82" . pack('E', $value->value);
        }

        if ($value instanceof Timestamp) {
            return "\x83" . $this->encodeSignedInt64($value->milliseconds);
        }

        if ($value instanceof Uuid) {
            return "\x98" . $this->encodeUuidBytes($value);
        }

        if ($value instanceof AmqpList) {
            return $this->encodeList($value);
        }

        if ($value instanceof Binary) {
            return $this->encodeVariableBytes($value->value, "\xa0", "\xb0");
        }

        if (is_string($value)) {
            return $this->encodeVariableBytes($value, "\xa1", "\xb1");
        }

        if ($value instanceof Symbol) {
            return $this->encodeVariableBytes($value->value, "\xa3", "\xb3");
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

    private function encodeUnsignedInt64(string $value): string
    {
        $bytes = array_fill(0, 8, "\x00");
        $remaining = $value;

        for ($index = 7; $index >= 0; --$index) {
            [$remaining, $remainder] = $this->decimalDivMod($remaining, 256);
            $bytes[$index] = chr($remainder);
        }

        return implode('', $bytes);
    }

    private function encodeUuidBytes(Uuid $value): string
    {
        $bytes = hex2bin(str_replace('-', '', $value->value));

        if ($bytes === false) {
            throw new LogicException('Canonical UUID could not be converted to bytes.');
        }

        return $bytes;
    }

    private function encodeList(AmqpList $list): string
    {
        if ($list->items === []) {
            return "\x45";
        }

        $payload = '';

        foreach ($list->items as $item) {
            $payload .= $this->encode($item);
        }

        $count = count($list->items);
        $smallSize = strlen($payload) + 1;

        if ($smallSize <= 255 && $count <= 255) {
            return "\xc0" . chr($smallSize) . chr($count) . $payload;
        }

        return "\xd0" . pack('N', strlen($payload) + 4) . pack('N', $count) . $payload;
    }

    /**
     * @return array{0: string, 1: int}
     */
    private function decimalDivMod(string $value, int $divisor): array
    {
        $quotient = '';
        $remainder = 0;

        for ($index = 0; $index < strlen($value); ++$index) {
            $number = ($remainder * 10) + (int) $value[$index];
            $digit = intdiv($number, $divisor);
            $remainder = $number % $divisor;

            if ($quotient !== '' || $digit !== 0) {
                $quotient .= (string) $digit;
            }
        }

        return [$quotient === '' ? '0' : $quotient, $remainder];
    }

    private function encodeVariableBytes(string $value, string $smallConstructor, string $largeConstructor): string
    {
        if (strlen($value) <= 255) {
            return $smallConstructor . chr(strlen($value)) . $value;
        }

        return $largeConstructor . pack('N', strlen($value)) . $value;
    }
}
