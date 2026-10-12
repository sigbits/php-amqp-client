<?php

declare(strict_types=1);

namespace Sigbits\Amqp\Protocol\Codec;

use InvalidArgumentException;
use Sigbits\Amqp\Protocol\Type\AmqpArray;
use Sigbits\Amqp\Protocol\Type\AmqpList;
use Sigbits\Amqp\Protocol\Type\AmqpMap;
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
        [$value] = $this->decodeValue($bytes, 0, strlen($bytes));

        return $value;
    }

    /**
     * @return array{0: mixed, 1: int}
     */
    private function decodeValue(string $bytes, int $cursor, int $end): array
    {
        if ($cursor >= $end) {
            throw DecodeException::truncatedValue();
        }

        $formatCode = ord($bytes[$cursor]);

        return match ($formatCode) {
            0x40 => [null, $cursor + 1],
            0x41 => [true, $cursor + 1],
            0x42 => [false, $cursor + 1],
            0x43 => [new UInt(0), $cursor + 1],
            0x44 => [new ULong(0), $cursor + 1],
            0x45 => [new AmqpList([]), $cursor + 1],
            0x50 => [$this->decodeUByte(substr($bytes, $cursor, 2)), $cursor + 2],
            0x51 => [$this->decodeByte(substr($bytes, $cursor, 2)), $cursor + 2],
            0x52 => [$this->decodeSmallUInt(substr($bytes, $cursor, 2)), $cursor + 2],
            0x53 => [$this->decodeSmallULong(substr($bytes, $cursor, 2)), $cursor + 2],
            0x56 => [$this->decodeBoolean(substr($bytes, $cursor, 2)), $cursor + 2],
            0x60 => [$this->decodeUShort(substr($bytes, $cursor, 3)), $cursor + 3],
            0x61 => [$this->decodeShort(substr($bytes, $cursor, 3)), $cursor + 3],
            0x70 => [$this->decodeUInt(substr($bytes, $cursor, 5)), $cursor + 5],
            0x71 => [$this->decodeInt(substr($bytes, $cursor, 5)), $cursor + 5],
            0x72 => [$this->decodeFloat32(substr($bytes, $cursor, 5)), $cursor + 5],
            0x73 => [$this->decodeChar(substr($bytes, $cursor, 5)), $cursor + 5],
            0x74 => [$this->decodeDecimal32(substr($bytes, $cursor, 5)), $cursor + 5],
            0x80 => [$this->decodeULong(substr($bytes, $cursor, 9)), $cursor + 9],
            0x81 => [$this->decodeLong(substr($bytes, $cursor, 9)), $cursor + 9],
            0x82 => [$this->decodeFloat64(substr($bytes, $cursor, 9)), $cursor + 9],
            0x83 => [$this->decodeTimestamp(substr($bytes, $cursor, 9)), $cursor + 9],
            0x84 => [$this->decodeDecimal64(substr($bytes, $cursor, 9)), $cursor + 9],
            0x94 => [$this->decodeDecimal128(substr($bytes, $cursor, 17)), $cursor + 17],
            0x98 => [$this->decodeUuid(substr($bytes, $cursor, 17)), $cursor + 17],
            0xa0 => $this->decodeVariableValue8($bytes, $cursor, $end, static fn (string $value): Binary => new Binary($value), DecodeException::truncatedBinary(...)),
            0xa1 => $this->decodeVariableValue8($bytes, $cursor, $end, static fn (string $value): string => $value, DecodeException::truncatedString(...)),
            0xa3 => $this->decodeVariableValue8($bytes, $cursor, $end, static fn (string $value): Symbol => new Symbol($value), DecodeException::truncatedSymbol(...)),
            0xb0 => $this->decodeVariableValue32($bytes, $cursor, $end, static fn (string $value): Binary => new Binary($value), DecodeException::truncatedBinary(...)),
            0xb1 => $this->decodeVariableValue32($bytes, $cursor, $end, static fn (string $value): string => $value, DecodeException::truncatedString(...)),
            0xb3 => $this->decodeVariableValue32($bytes, $cursor, $end, static fn (string $value): Symbol => new Symbol($value), DecodeException::truncatedSymbol(...)),
            0xc0 => $this->decodeList8($bytes, $cursor, $end),
            0xc1 => $this->decodeMap8($bytes, $cursor, $end),
            0xd0 => $this->decodeList32($bytes, $cursor, $end),
            0xd1 => $this->decodeMap32($bytes, $cursor, $end),
            0xe0 => $this->decodeArray8($bytes, $cursor, $end),
            0xf0 => $this->decodeArray32($bytes, $cursor, $end),
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

    /**
     * @param callable(string): mixed $factory
     * @param callable(): DecodeException $truncated
     *
     * @return array{0: mixed, 1: int}
     */
    private function decodeVariableValue8(string $bytes, int $cursor, int $end, callable $factory, callable $truncated): array
    {
        if ($end - $cursor < 2) {
            throw $truncated();
        }

        $length = ord($bytes[$cursor + 1]);
        $next = $cursor + 2 + $length;

        if ($next > $end) {
            throw $truncated();
        }

        return [$factory(substr($bytes, $cursor + 2, $length)), $next];
    }

    /**
     * @param callable(string): mixed $factory
     * @param callable(): DecodeException $truncated
     *
     * @return array{0: mixed, 1: int}
     */
    private function decodeVariableValue32(string $bytes, int $cursor, int $end, callable $factory, callable $truncated): array
    {
        if ($end - $cursor < 5) {
            throw $truncated();
        }

        $length = (ord($bytes[$cursor + 1]) << 24)
            | (ord($bytes[$cursor + 2]) << 16)
            | (ord($bytes[$cursor + 3]) << 8)
            | ord($bytes[$cursor + 4]);
        $next = $cursor + 5 + $length;

        if ($next > $end) {
            throw $truncated();
        }

        return [$factory(substr($bytes, $cursor + 5, $length)), $next];
    }

    /**
     * @return array{0: AmqpList, 1: int}
     */
    private function decodeList8(string $bytes, int $cursor, int $end): array
    {
        if ($end - $cursor < 3) {
            throw DecodeException::truncatedList();
        }

        $size = ord($bytes[$cursor + 1]);

        if ($size < 1) {
            throw DecodeException::malformedList();
        }

        $listEnd = $cursor + 2 + $size;

        if ($listEnd > $end) {
            throw DecodeException::truncatedList();
        }

        return [$this->decodeListItems($bytes, $cursor + 3, $listEnd, ord($bytes[$cursor + 2])), $listEnd];
    }

    /**
     * @return array{0: AmqpList, 1: int}
     */
    private function decodeList32(string $bytes, int $cursor, int $end): array
    {
        if ($end - $cursor < 9) {
            throw DecodeException::truncatedList();
        }

        $size = (ord($bytes[$cursor + 1]) << 24)
            | (ord($bytes[$cursor + 2]) << 16)
            | (ord($bytes[$cursor + 3]) << 8)
            | ord($bytes[$cursor + 4]);

        if ($size < 4) {
            throw DecodeException::malformedList();
        }

        $listEnd = $cursor + 5 + $size;

        if ($listEnd > $end) {
            throw DecodeException::truncatedList();
        }

        $count = (ord($bytes[$cursor + 5]) << 24)
            | (ord($bytes[$cursor + 6]) << 16)
            | (ord($bytes[$cursor + 7]) << 8)
            | ord($bytes[$cursor + 8]);

        return [$this->decodeListItems($bytes, $cursor + 9, $listEnd, $count), $listEnd];
    }

    private function decodeListItems(string $bytes, int $cursor, int $end, int $count): AmqpList
    {
        $items = [];

        for ($index = 0; $index < $count; ++$index) {
            if ($cursor >= $end) {
                throw DecodeException::malformedList();
            }

            try {
                [$item, $cursor] = $this->decodeValue($bytes, $cursor, $end);
                $items[] = $item;
            } catch (DecodeException $exception) {
                if (str_starts_with($exception->getMessage(), 'Truncated AMQP')) {
                    throw DecodeException::truncatedList();
                }

                throw $exception;
            }
        }

        if ($cursor !== $end) {
            throw DecodeException::malformedList();
        }

        try {
            return new AmqpList($items);
        } catch (InvalidArgumentException) {
            throw DecodeException::malformedList();
        }
    }

    /**
     * @return array{0: AmqpMap, 1: int}
     */
    private function decodeMap8(string $bytes, int $cursor, int $end): array
    {
        if ($end - $cursor < 3) {
            throw DecodeException::truncatedMap();
        }

        $size = ord($bytes[$cursor + 1]);

        if ($size < 1) {
            throw DecodeException::malformedMap();
        }

        $mapEnd = $cursor + 2 + $size;

        if ($mapEnd > $end) {
            throw DecodeException::truncatedMap();
        }

        return [$this->decodeMapItems($bytes, $cursor + 3, $mapEnd, ord($bytes[$cursor + 2])), $mapEnd];
    }

    /**
     * @return array{0: AmqpMap, 1: int}
     */
    private function decodeMap32(string $bytes, int $cursor, int $end): array
    {
        if ($end - $cursor < 9) {
            throw DecodeException::truncatedMap();
        }

        $size = (ord($bytes[$cursor + 1]) << 24)
            | (ord($bytes[$cursor + 2]) << 16)
            | (ord($bytes[$cursor + 3]) << 8)
            | ord($bytes[$cursor + 4]);

        if ($size < 4) {
            throw DecodeException::malformedMap();
        }

        $mapEnd = $cursor + 5 + $size;

        if ($mapEnd > $end) {
            throw DecodeException::truncatedMap();
        }

        $count = (ord($bytes[$cursor + 5]) << 24)
            | (ord($bytes[$cursor + 6]) << 16)
            | (ord($bytes[$cursor + 7]) << 8)
            | ord($bytes[$cursor + 8]);

        return [$this->decodeMapItems($bytes, $cursor + 9, $mapEnd, $count), $mapEnd];
    }

    private function decodeMapItems(string $bytes, int $cursor, int $end, int $count): AmqpMap
    {
        if ($count % 2 !== 0) {
            throw DecodeException::malformedMap();
        }

        $pairs = [];

        for ($index = 0; $index < $count; $index += 2) {
            if ($cursor >= $end) {
                throw DecodeException::malformedMap();
            }

            try {
                [$key, $cursor] = $this->decodeValue($bytes, $cursor, $end);

                if ($cursor >= $end) {
                    throw DecodeException::malformedMap();
                }

                [$value, $cursor] = $this->decodeValue($bytes, $cursor, $end);
                $pairs[] = [$key, $value];
            } catch (DecodeException $exception) {
                if (str_starts_with($exception->getMessage(), 'Truncated AMQP')) {
                    throw DecodeException::truncatedMap();
                }

                throw $exception;
            }
        }

        if ($cursor !== $end) {
            throw DecodeException::malformedMap();
        }

        try {
            return new AmqpMap($pairs);
        } catch (InvalidArgumentException) {
            throw DecodeException::malformedMap();
        }
    }

    /**
     * @return array{0: AmqpArray, 1: int}
     */
    private function decodeArray8(string $bytes, int $cursor, int $end): array
    {
        if ($end - $cursor < 3) {
            throw DecodeException::truncatedArray();
        }

        $size = ord($bytes[$cursor + 1]);

        if ($size < 2) {
            throw DecodeException::malformedArray();
        }

        $arrayEnd = $cursor + 2 + $size;

        if ($arrayEnd > $end) {
            throw DecodeException::truncatedArray();
        }

        return $this->decodeArrayItems($bytes, $cursor + 3, $arrayEnd, ord($bytes[$cursor + 2]));
    }

    /**
     * @return array{0: AmqpArray, 1: int}
     */
    private function decodeArray32(string $bytes, int $cursor, int $end): array
    {
        if ($end - $cursor < 9) {
            throw DecodeException::truncatedArray();
        }

        $size = (ord($bytes[$cursor + 1]) << 24)
            | (ord($bytes[$cursor + 2]) << 16)
            | (ord($bytes[$cursor + 3]) << 8)
            | ord($bytes[$cursor + 4]);

        if ($size < 5) {
            throw DecodeException::malformedArray();
        }

        $arrayEnd = $cursor + 5 + $size;

        if ($arrayEnd > $end) {
            throw DecodeException::truncatedArray();
        }

        $count = (ord($bytes[$cursor + 5]) << 24)
            | (ord($bytes[$cursor + 6]) << 16)
            | (ord($bytes[$cursor + 7]) << 8)
            | ord($bytes[$cursor + 8]);

        return $this->decodeArrayItems($bytes, $cursor + 9, $arrayEnd, $count);
    }

    /**
     * @return array{0: AmqpArray, 1: int}
     */
    private function decodeArrayItems(string $bytes, int $cursor, int $end, int $count): array
    {
        if ($cursor >= $end) {
            throw DecodeException::truncatedArray();
        }

        $formatCode = ord($bytes[$cursor]);
        $elementConstructor = $bytes[$cursor];
        ++$cursor;

        $items = [];

        for ($index = 0; $index < $count; ++$index) {
            try {
                [$item, $cursor] = $this->decodeArrayElement($formatCode, $bytes, $cursor, $end);
                $items[] = $item;
            } catch (DecodeException $exception) {
                if (str_starts_with($exception->getMessage(), 'Truncated AMQP')) {
                    throw DecodeException::truncatedArray();
                }

                throw $exception;
            }
        }

        if ($cursor !== $end) {
            throw DecodeException::malformedArray();
        }

        try {
            return [new AmqpArray($elementConstructor, $items), $end];
        } catch (InvalidArgumentException) {
            throw DecodeException::malformedArray();
        }
    }

    /**
     * @return array{0: mixed, 1: int}
     */
    private function decodeArrayElement(int $formatCode, string $bytes, int $cursor, int $end): array
    {
        return match ($formatCode) {
            0x40 => [null, $cursor],
            0x41 => [true, $cursor],
            0x42 => [false, $cursor],
            0x43 => [new UInt(0), $cursor],
            0x44 => [new ULong(0), $cursor],
            0x45 => [new AmqpList([]), $cursor],
            0x50 => [$this->decodeUByte("\x50" . substr($bytes, $cursor, 1)), $cursor + 1],
            0x51 => [$this->decodeByte("\x51" . substr($bytes, $cursor, 1)), $cursor + 1],
            0x52 => [$this->decodeSmallUInt("\x52" . substr($bytes, $cursor, 1)), $cursor + 1],
            0x53 => [$this->decodeSmallULong("\x53" . substr($bytes, $cursor, 1)), $cursor + 1],
            0x56 => [$this->decodeBoolean("\x56" . substr($bytes, $cursor, 1)), $cursor + 1],
            0x60 => [$this->decodeUShort("\x60" . substr($bytes, $cursor, 2)), $cursor + 2],
            0x61 => [$this->decodeShort("\x61" . substr($bytes, $cursor, 2)), $cursor + 2],
            0x70 => [$this->decodeUInt("\x70" . substr($bytes, $cursor, 4)), $cursor + 4],
            0x71 => [$this->decodeInt("\x71" . substr($bytes, $cursor, 4)), $cursor + 4],
            0x72 => [$this->decodeFloat32("\x72" . substr($bytes, $cursor, 4)), $cursor + 4],
            0x73 => [$this->decodeChar("\x73" . substr($bytes, $cursor, 4)), $cursor + 4],
            0x74 => [$this->decodeDecimal32("\x74" . substr($bytes, $cursor, 4)), $cursor + 4],
            0x80 => [$this->decodeULong("\x80" . substr($bytes, $cursor, 8)), $cursor + 8],
            0x81 => [$this->decodeLong("\x81" . substr($bytes, $cursor, 8)), $cursor + 8],
            0x82 => [$this->decodeFloat64("\x82" . substr($bytes, $cursor, 8)), $cursor + 8],
            0x83 => [$this->decodeTimestamp("\x83" . substr($bytes, $cursor, 8)), $cursor + 8],
            0x84 => [$this->decodeDecimal64("\x84" . substr($bytes, $cursor, 8)), $cursor + 8],
            0x94 => [$this->decodeDecimal128("\x94" . substr($bytes, $cursor, 16)), $cursor + 16],
            0x98 => [$this->decodeUuid("\x98" . substr($bytes, $cursor, 16)), $cursor + 16],
            0xa0 => $this->decodeArrayElementVariable($bytes, $cursor, $end, "\xa0", $this->decodeVariableValue8(...), static fn (string $value): Binary => new Binary($value), DecodeException::truncatedBinary(...)),
            0xa1 => $this->decodeArrayElementVariable($bytes, $cursor, $end, "\xa1", $this->decodeVariableValue8(...), static fn (string $value): string => $value, DecodeException::truncatedString(...)),
            0xa3 => $this->decodeArrayElementVariable($bytes, $cursor, $end, "\xa3", $this->decodeVariableValue8(...), static fn (string $value): Symbol => new Symbol($value), DecodeException::truncatedSymbol(...)),
            0xb0 => $this->decodeArrayElementVariable($bytes, $cursor, $end, "\xb0", $this->decodeVariableValue32(...), static fn (string $value): Binary => new Binary($value), DecodeException::truncatedBinary(...)),
            0xb1 => $this->decodeArrayElementVariable($bytes, $cursor, $end, "\xb1", $this->decodeVariableValue32(...), static fn (string $value): string => $value, DecodeException::truncatedString(...)),
            0xb3 => $this->decodeArrayElementVariable($bytes, $cursor, $end, "\xb3", $this->decodeVariableValue32(...), static fn (string $value): Symbol => new Symbol($value), DecodeException::truncatedSymbol(...)),
            0xc0 => $this->decodeArrayElementCompound($bytes, $cursor, $end, "\xc0", $this->decodeList8(...)),
            0xc1 => $this->decodeArrayElementCompound($bytes, $cursor, $end, "\xc1", $this->decodeMap8(...)),
            0xd0 => $this->decodeArrayElementCompound($bytes, $cursor, $end, "\xd0", $this->decodeList32(...)),
            0xd1 => $this->decodeArrayElementCompound($bytes, $cursor, $end, "\xd1", $this->decodeMap32(...)),
            0xe0 => $this->decodeArrayElementCompound($bytes, $cursor, $end, "\xe0", $this->decodeArray8(...)),
            0xf0 => $this->decodeArrayElementCompound($bytes, $cursor, $end, "\xf0", $this->decodeArray32(...)),
            default => throw DecodeException::unsupportedFormatCode($formatCode),
        };
    }

    /**
     * @param callable(string, int, int): array{0: mixed, 1: int} $decoder
     *
     * @return array{0: mixed, 1: int}
     */
    private function decodeArrayElementCompound(string $bytes, int $cursor, int $end, string $constructor, callable $decoder): array
    {
        [$value, $next] = $decoder($constructor . substr($bytes, $cursor, $end - $cursor), 0, $end - $cursor + 1);

        return [$value, $cursor + $next - 1];
    }

    /**
     * @param callable(string, int, int, callable, callable): array{0: mixed, 1: int} $decoder
     * @param callable(string): mixed $factory
     * @param callable(): DecodeException $truncated
     *
     * @return array{0: mixed, 1: int}
     */
    private function decodeArrayElementVariable(
        string $bytes,
        int $cursor,
        int $end,
        string $constructor,
        callable $decoder,
        callable $factory,
        callable $truncated,
    ): array {
        [$value, $next] = $decoder($constructor . substr($bytes, $cursor, $end - $cursor), 0, $end - $cursor + 1, $factory, $truncated);

        return [$value, $cursor + $next - 1];
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
