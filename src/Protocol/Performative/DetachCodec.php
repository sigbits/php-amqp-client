<?php

declare(strict_types=1);

namespace Sigbits\Amqp\Protocol\Performative;

use Sigbits\Amqp\Protocol\Codec\DecodeException;
use Sigbits\Amqp\Protocol\Codec\ScalarReader;
use Sigbits\Amqp\Protocol\Type\UInt;

final class DetachCodec
{
    private const string DESCRIPTOR = "\x00\x53\x16";
    private const int DESCRIPTOR_LENGTH = 3;
    private const int CONSTRUCTOR_LIST0 = 0x45;
    private const int CONSTRUCTOR_LIST8 = 0xc0;
    private const int CONSTRUCTOR_NULL = 0x40;
    private const int CONSTRUCTOR_BOOL_TRUE = 0x41;
    private const int CONSTRUCTOR_BOOL_FALSE = 0x42;
    private const int CONSTRUCTOR_BOOL = 0x56;
    private const int CONSTRUCTOR_UINT = 0x70;
    private const int CONSTRUCTOR_STR8 = 0xa1;
    private const int CONSTRUCTOR_SYM8 = 0xa3;
    private const string ERROR_DESCRIPTOR = "\x00\x53\x1d";

    private readonly ScalarReader $scalarReader;

    public function __construct(?ScalarReader $scalarReader = null)
    {
        $this->scalarReader = $scalarReader ?? new ScalarReader();
    }

    public function encode(Detach $detach): string
    {
        $fields = $this->encodeUInt($detach->handle);
        $listSize = 1 + strlen($fields);

        return self::DESCRIPTOR
            . chr(self::CONSTRUCTOR_LIST8)
            . chr($listSize)
            . "\x01"
            . $fields;
    }

    public function decode(string $bytes): Detach
    {
        if (strlen($bytes) < self::DESCRIPTOR_LENGTH + 1) {
            throw PerformativeException::truncatedDetach();
        }

        if (substr($bytes, 0, self::DESCRIPTOR_LENGTH) !== self::DESCRIPTOR) {
            throw PerformativeException::expectedDetachDescriptor();
        }

        $cursor = self::DESCRIPTOR_LENGTH;
        $constructor = ord($bytes[$cursor]);
        ++$cursor;

        if ($constructor === self::CONSTRUCTOR_LIST0) {
            throw PerformativeException::missingDetachHandle();
        }

        if ($constructor !== self::CONSTRUCTOR_LIST8) {
            throw PerformativeException::truncatedDetach();
        }

        if (strlen($bytes) < $cursor + 2) {
            throw PerformativeException::truncatedDetach();
        }

        $listSize = ord($bytes[$cursor]);
        ++$cursor;
        $fieldCount = ord($bytes[$cursor]);
        ++$cursor;

        if ($fieldCount < 1) {
            throw PerformativeException::missingDetachHandle();
        }

        $listEnd = $cursor + $listSize - 1;

        if (strlen($bytes) < $listEnd) {
            throw PerformativeException::truncatedDetach();
        }

        [$handle, $cursor] = $this->decodeUInt($bytes, $cursor, $listEnd);
        [$closed, $cursor] = $this->decodeOptionalBool($bytes, $cursor, $listEnd, $fieldCount >= 2);
        $error = $fieldCount >= 3 && $cursor < $listEnd ? $this->decodeError($bytes, $cursor, $listEnd) : null;

        return new Detach(handle: $handle, closed: $closed, error: $error);
    }

    private function encodeUInt(int $value): string
    {
        return chr(self::CONSTRUCTOR_UINT) . pack('N', (new UInt($value))->value);
    }

    /**
     * @return array{0: int, 1: int}
     */
    private function decodeUInt(string $bytes, int $cursor, int $listEnd): array
    {
        try {
            return $this->scalarReader->readUInt($bytes, $cursor, $listEnd);
        } catch (DecodeException $exception) {
            if (str_starts_with($exception->getMessage(), 'Unsupported AMQP format code')) {
                throw PerformativeException::missingDetachHandle();
            }

            throw PerformativeException::truncatedDetach();
        }
    }

    /**
     * @return array{0: ?bool, 1: int}
     */
    private function decodeOptionalBool(string $bytes, int $cursor, int $listEnd, bool $present): array
    {
        if (!$present || $cursor >= $listEnd) {
            return [null, $cursor];
        }

        $constructor = ord($bytes[$cursor]);
        ++$cursor;

        if ($constructor === self::CONSTRUCTOR_NULL) {
            return [null, $cursor];
        }

        if ($constructor === self::CONSTRUCTOR_BOOL_TRUE) {
            return [true, $cursor];
        }

        if ($constructor === self::CONSTRUCTOR_BOOL_FALSE) {
            return [false, $cursor];
        }

        if ($constructor !== self::CONSTRUCTOR_BOOL) {
            throw PerformativeException::truncatedDetach();
        }

        if ($cursor >= $listEnd) {
            throw PerformativeException::truncatedDetach();
        }

        return [ord($bytes[$cursor]) !== 0, $cursor + 1];
    }

    private function decodeError(string $bytes, int $cursor, int $listEnd): ?PerformativeError
    {
        if ($cursor >= $listEnd) {
            return null;
        }

        if (ord($bytes[$cursor]) === self::CONSTRUCTOR_NULL) {
            return null;
        }

        if ($cursor + self::DESCRIPTOR_LENGTH > $listEnd || substr($bytes, $cursor, self::DESCRIPTOR_LENGTH) !== self::ERROR_DESCRIPTOR) {
            throw PerformativeException::truncatedDetach();
        }

        $cursor += self::DESCRIPTOR_LENGTH;

        if ($cursor >= $listEnd || ord($bytes[$cursor]) !== self::CONSTRUCTOR_LIST8) {
            throw PerformativeException::truncatedDetach();
        }

        ++$cursor;

        if ($cursor + 2 > $listEnd) {
            throw PerformativeException::truncatedDetach();
        }

        $errorListSize = ord($bytes[$cursor]);
        ++$cursor;
        $errorFieldCount = ord($bytes[$cursor]);
        ++$cursor;
        $errorListEnd = $cursor + $errorListSize - 1;

        if ($errorFieldCount < 1 || $errorListEnd > $listEnd || strlen($bytes) < $errorListEnd) {
            throw PerformativeException::truncatedDetach();
        }

        [$condition, $cursor] = $this->decodeSymbol($bytes, $cursor, $errorListEnd);
        [$description] = $this->decodeOptionalString($bytes, $cursor, $errorListEnd, $errorFieldCount >= 2);

        return new PerformativeError(condition: $condition, description: $description);
    }

    /**
     * @return array{0: string, 1: int}
     */
    private function decodeSymbol(string $bytes, int $cursor, int $listEnd): array
    {
        if ($cursor >= $listEnd || ord($bytes[$cursor]) !== self::CONSTRUCTOR_SYM8) {
            throw PerformativeException::truncatedDetach();
        }

        return $this->decodeVariableString($bytes, $cursor + 1, $listEnd);
    }

    /**
     * @return array{0: ?string, 1: int}
     */
    private function decodeOptionalString(string $bytes, int $cursor, int $listEnd, bool $present): array
    {
        if (!$present || $cursor >= $listEnd) {
            return [null, $cursor];
        }

        $constructor = ord($bytes[$cursor]);
        ++$cursor;

        if ($constructor === self::CONSTRUCTOR_NULL) {
            return [null, $cursor];
        }

        if ($constructor !== self::CONSTRUCTOR_STR8) {
            throw PerformativeException::truncatedDetach();
        }

        return $this->decodeVariableString($bytes, $cursor, $listEnd);
    }

    /**
     * @return array{0: string, 1: int}
     */
    private function decodeVariableString(string $bytes, int $cursor, int $listEnd): array
    {
        if ($cursor >= $listEnd) {
            throw PerformativeException::truncatedDetach();
        }

        $length = ord($bytes[$cursor]);
        ++$cursor;

        if ($cursor + $length > $listEnd) {
            throw PerformativeException::truncatedDetach();
        }

        return [substr($bytes, $cursor, $length), $cursor + $length];
    }
}
