<?php

declare(strict_types=1);

namespace Sigbits\Amqp\Protocol\Performative;

use Sigbits\Amqp\Protocol\Type\UInt;

final class AttachCodec
{
    private const string DESCRIPTOR = "\x00\x53\x12";
    private const int DESCRIPTOR_LENGTH = 3;
    private const int CONSTRUCTOR_LIST0 = 0x45;
    private const int CONSTRUCTOR_LIST8 = 0xc0;
    private const int CONSTRUCTOR_STR8 = 0xa1;
    private const int CONSTRUCTOR_BOOL_TRUE = 0x41;
    private const int CONSTRUCTOR_BOOL_FALSE = 0x42;
    private const int CONSTRUCTOR_NULL = 0x40;
    private const int CONSTRUCTOR_UINT0 = 0x43;
    private const int CONSTRUCTOR_SMALLUINT = 0x52;
    private const int CONSTRUCTOR_BOOL = 0x56;
    private const int CONSTRUCTOR_UINT = 0x70;
    private const string SOURCE_DESCRIPTOR = "\x00\x53\x28";
    private const string TARGET_DESCRIPTOR = "\x00\x53\x29";

    public function encode(Attach $attach): string
    {
        $fields = $this->encodeString($attach->name)
            . $this->encodeUInt($attach->handle)
            . ($attach->role === LinkRole::Receiver ? "\x41" : "\x42");
        $fieldCount = 3;

        if ($attach->sourceAddress !== null || $attach->targetAddress !== null || $attach->initialDeliveryCount !== null) {
            $fields .= chr(self::CONSTRUCTOR_NULL)
                . chr(self::CONSTRUCTOR_NULL)
                . ($attach->sourceAddress !== null ? $this->encodeSource($attach->sourceAddress) : chr(self::CONSTRUCTOR_NULL))
                . ($attach->targetAddress !== null ? $this->encodeTarget($attach->targetAddress) : chr(self::CONSTRUCTOR_NULL));
            $fieldCount = 7;
        }

        if ($attach->initialDeliveryCount !== null) {
            $fields .= chr(self::CONSTRUCTOR_NULL)
                . chr(self::CONSTRUCTOR_NULL)
                . $this->encodeCompactUInt($attach->initialDeliveryCount);
            $fieldCount = 10;
        }

        $listSize = 1 + strlen($fields);

        return self::DESCRIPTOR
            . chr(self::CONSTRUCTOR_LIST8)
            . chr($listSize)
            . chr($fieldCount)
            . $fields;
    }

    public function decode(string $bytes): Attach
    {
        if (strlen($bytes) < self::DESCRIPTOR_LENGTH + 1) {
            throw PerformativeException::truncatedAttach();
        }

        if (substr($bytes, 0, self::DESCRIPTOR_LENGTH) !== self::DESCRIPTOR) {
            throw PerformativeException::expectedAttachDescriptor();
        }

        $cursor = self::DESCRIPTOR_LENGTH;
        $constructor = ord($bytes[$cursor]);
        ++$cursor;

        if ($constructor === self::CONSTRUCTOR_LIST0) {
            throw PerformativeException::missingAttachRequiredFields();
        }

        if ($constructor !== self::CONSTRUCTOR_LIST8) {
            throw PerformativeException::truncatedAttach();
        }

        if (strlen($bytes) < $cursor + 2) {
            throw PerformativeException::truncatedAttach();
        }

        $listSize = ord($bytes[$cursor]);
        ++$cursor;
        $fieldCount = ord($bytes[$cursor]);
        ++$cursor;

        if ($fieldCount < 3) {
            throw PerformativeException::missingAttachRequiredFields();
        }

        $listEnd = $cursor + $listSize - 1;

        if (strlen($bytes) < $listEnd) {
            throw PerformativeException::truncatedAttach();
        }

        [$name, $cursor] = $this->decodeString($bytes, $cursor, $listEnd);
        [$handle, $cursor] = $this->decodeUInt($bytes, $cursor, $listEnd);
        [$role] = $this->decodeRole($bytes, $cursor, $listEnd);

        return new Attach(
            name: $name,
            handle: $handle,
            role: $role,
        );
    }

    private function encodeString(string $value): string
    {
        return chr(self::CONSTRUCTOR_STR8) . chr(strlen($value)) . $value;
    }

    private function encodeUInt(int $value): string
    {
        return chr(self::CONSTRUCTOR_UINT) . pack('N', (new UInt($value))->value);
    }

    private function encodeCompactUInt(int $value): string
    {
        new UInt($value);

        if ($value === 0) {
            return chr(self::CONSTRUCTOR_UINT0);
        }

        if ($value <= 255) {
            return chr(self::CONSTRUCTOR_SMALLUINT) . chr($value);
        }

        return $this->encodeUInt($value);
    }

    private function encodeSource(string $address): string
    {
        return self::SOURCE_DESCRIPTOR
            . chr(self::CONSTRUCTOR_LIST8)
            . chr(1 + strlen($this->encodeString($address)))
            . "\x01"
            . $this->encodeString($address);
    }

    private function encodeTarget(string $address): string
    {
        return self::TARGET_DESCRIPTOR
            . chr(self::CONSTRUCTOR_LIST8)
            . chr(1 + strlen($this->encodeString($address)))
            . "\x01"
            . $this->encodeString($address);
    }

    /**
     * @return array{0: string, 1: int}
     */
    private function decodeString(string $bytes, int $cursor, int $listEnd): array
    {
        if ($cursor >= $listEnd) {
            throw PerformativeException::truncatedAttach();
        }

        if (ord($bytes[$cursor]) !== self::CONSTRUCTOR_STR8) {
            throw PerformativeException::missingAttachRequiredFields();
        }

        ++$cursor;

        if ($cursor >= $listEnd) {
            throw PerformativeException::truncatedAttach();
        }

        $length = ord($bytes[$cursor]);
        ++$cursor;

        if ($cursor + $length > $listEnd) {
            throw PerformativeException::truncatedAttach();
        }

        return [
            substr($bytes, $cursor, $length),
            $cursor + $length,
        ];
    }

    /**
     * @return array{0: int, 1: int}
     */
    private function decodeUInt(string $bytes, int $cursor, int $listEnd): array
    {
        if ($cursor >= $listEnd) {
            throw PerformativeException::truncatedAttach();
        }

        $constructor = ord($bytes[$cursor]);
        ++$cursor;

        if ($constructor === self::CONSTRUCTOR_UINT0) {
            return [0, $cursor];
        }

        if ($constructor === self::CONSTRUCTOR_SMALLUINT) {
            if ($cursor >= $listEnd) {
                throw PerformativeException::truncatedAttach();
            }

            return [ord($bytes[$cursor]), $cursor + 1];
        }

        if ($constructor !== self::CONSTRUCTOR_UINT) {
            throw PerformativeException::missingAttachRequiredFields();
        }

        if ($cursor + 4 > $listEnd) {
            throw PerformativeException::truncatedAttach();
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
     * @return array{0: LinkRole, 1: int}
     */
    private function decodeRole(string $bytes, int $cursor, int $listEnd): array
    {
        if ($cursor >= $listEnd) {
            throw PerformativeException::truncatedAttach();
        }

        $constructor = ord($bytes[$cursor]);

        return match ($constructor) {
            self::CONSTRUCTOR_BOOL_FALSE => [LinkRole::Sender, $cursor + 1],
            self::CONSTRUCTOR_BOOL_TRUE => [LinkRole::Receiver, $cursor + 1],
            self::CONSTRUCTOR_BOOL => $this->decodeBooleanRole($bytes, $cursor, $listEnd),
            default => throw PerformativeException::missingAttachRequiredFields(),
        };
    }

    /**
     * @return array{0: LinkRole, 1: int}
     */
    private function decodeBooleanRole(string $bytes, int $cursor, int $listEnd): array
    {
        ++$cursor;

        if ($cursor >= $listEnd) {
            throw PerformativeException::truncatedAttach();
        }

        return [
            ord($bytes[$cursor]) === 0 ? LinkRole::Sender : LinkRole::Receiver,
            $cursor + 1,
        ];
    }
}
