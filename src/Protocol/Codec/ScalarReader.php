<?php

declare(strict_types=1);

namespace Sigbits\Amqp\Protocol\Codec;

final class ScalarReader
{
    private const int CONSTRUCTOR_NULL = 0x40;
    private const int CONSTRUCTOR_BOOL_TRUE = 0x41;
    private const int CONSTRUCTOR_BOOL_FALSE = 0x42;
    private const int CONSTRUCTOR_UINT0 = 0x43;
    private const int CONSTRUCTOR_SMALLUINT = 0x52;
    private const int CONSTRUCTOR_UINT = 0x70;

    /**
     * @return array{0: int|null, 1: int}
     */
    public function readNullableUInt(string $bytes, int $cursor, int $end): array
    {
        if ($cursor >= $end) {
            throw DecodeException::truncatedUInt();
        }

        $constructor = ord($bytes[$cursor]);
        ++$cursor;

        if ($constructor === self::CONSTRUCTOR_NULL) {
            return [null, $cursor];
        }

        if ($constructor === self::CONSTRUCTOR_UINT0) {
            return [0, $cursor];
        }

        if ($constructor === self::CONSTRUCTOR_SMALLUINT) {
            if ($cursor >= $end) {
                throw DecodeException::truncatedUInt();
            }

            return [ord($bytes[$cursor]), $cursor + 1];
        }

        if ($constructor !== self::CONSTRUCTOR_UINT) {
            throw DecodeException::unsupportedFormatCode($constructor);
        }

        if ($cursor + 4 > $end) {
            throw DecodeException::truncatedUInt();
        }

        return [
            (ord($bytes[$cursor]) << 24)
            | (ord($bytes[$cursor + 1]) << 16)
            | (ord($bytes[$cursor + 2]) << 8)
            | ord($bytes[$cursor + 3]),
            $cursor + 4,
        ];
    }

    /**
     * @return array{0: int, 1: int}
     */
    public function readUInt(string $bytes, int $cursor, int $end): array
    {
        [$value, $cursor] = $this->readNullableUInt($bytes, $cursor, $end);

        if ($value === null) {
            throw DecodeException::unsupportedFormatCode(self::CONSTRUCTOR_NULL);
        }

        return [$value, $cursor];
    }

    public function skipValue(string $bytes, int $cursor, int $end): int
    {
        if ($cursor >= $end) {
            throw DecodeException::truncatedValue();
        }

        return match (ord($bytes[$cursor])) {
            self::CONSTRUCTOR_NULL,
            self::CONSTRUCTOR_BOOL_TRUE,
            self::CONSTRUCTOR_BOOL_FALSE,
            self::CONSTRUCTOR_UINT0 => $cursor + 1,
            self::CONSTRUCTOR_SMALLUINT => $cursor + 2 <= $end ? $cursor + 2 : throw DecodeException::truncatedUInt(),
            self::CONSTRUCTOR_UINT => $cursor + 5 <= $end ? $cursor + 5 : throw DecodeException::truncatedUInt(),
            default => throw DecodeException::unsupportedFormatCode(ord($bytes[$cursor])),
        };
    }
}
