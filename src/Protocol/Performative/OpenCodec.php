<?php

declare(strict_types=1);

namespace Sigbits\Amqp\Protocol\Performative;

final class OpenCodec
{
    private const string DESCRIPTOR = "\x00\x53\x10";
    private const int DESCRIPTOR_LENGTH = 3;
    private const int CONSTRUCTOR_LIST0 = 0x45;
    private const int CONSTRUCTOR_LIST8 = 0xc0;
    private const int CONSTRUCTOR_LIST32 = 0xd0;
    private const int CONSTRUCTOR_STR8 = 0xa1;
    private const int CONSTRUCTOR_STR32 = 0xb1;

    public function encode(Open $open): string
    {
        $containerIdLength = strlen($open->containerId);
        $fieldCount = $open->hostname === null ? 1 : 2;
        $listSize = 1 + 1 + 1 + $containerIdLength;

        if ($open->hostname !== null) {
            $listSize += 1 + 1 + strlen($open->hostname);
        }

        $payload = self::DESCRIPTOR
            . chr(self::CONSTRUCTOR_LIST8)
            . chr($listSize)
            . chr($fieldCount)
            . chr(self::CONSTRUCTOR_STR8)
            . chr($containerIdLength)
            . $open->containerId;

        if ($open->hostname !== null) {
            $hostnameLength = strlen($open->hostname);

            $payload .= chr(self::CONSTRUCTOR_STR8)
                . chr($hostnameLength)
                . $open->hostname;
        }

        return $payload;
    }

    public function decode(string $bytes): Open
    {
        if (strlen($bytes) < self::DESCRIPTOR_LENGTH + 1) {
            throw PerformativeException::truncatedOpen();
        }

        if (substr($bytes, 0, self::DESCRIPTOR_LENGTH) !== self::DESCRIPTOR) {
            throw PerformativeException::expectedOpenDescriptor();
        }

        $cursor = self::DESCRIPTOR_LENGTH;
        $constructor = ord($bytes[$cursor]);
        ++$cursor;

        if ($constructor === self::CONSTRUCTOR_LIST0) {
            throw PerformativeException::missingOpenContainerId();
        }

        if ($constructor === self::CONSTRUCTOR_LIST8) {
            if (strlen($bytes) < $cursor + 2) {
                throw PerformativeException::truncatedOpen();
            }

            $listSize = ord($bytes[$cursor]);
            ++$cursor;
            $fieldCount = ord($bytes[$cursor]);
            ++$cursor;
            $listEnd = $cursor + $listSize - 1;
        } elseif ($constructor === self::CONSTRUCTOR_LIST32) {
            if (strlen($bytes) < $cursor + 8) {
                throw PerformativeException::truncatedOpen();
            }

            $listSize = $this->readUInt32($bytes, $cursor);
            $cursor += 4;
            $fieldCount = $this->readUInt32($bytes, $cursor);
            $cursor += 4;

            if ($listSize < 4) {
                throw PerformativeException::truncatedOpen();
            }

            $listEnd = $cursor + $listSize - 4;
        } else {
            throw PerformativeException::truncatedOpen();
        }

        if ($fieldCount < 1) {
            throw PerformativeException::missingOpenContainerId();
        }

        if (strlen($bytes) < $listEnd || $cursor >= $listEnd) {
            throw PerformativeException::truncatedOpen();
        }

        $containerId = $this->readString($bytes, $cursor, $listEnd)
            ?? throw PerformativeException::missingOpenContainerId();
        $hostname = null;

        if ($fieldCount >= 2 && $cursor < $listEnd) {
            $hostname = $this->readString($bytes, $cursor, $listEnd);
        }

        return new Open($containerId, $hostname);
    }

    private function readUInt32(string $bytes, int $cursor): int
    {
        return (ord($bytes[$cursor]) << 24)
            | (ord($bytes[$cursor + 1]) << 16)
            | (ord($bytes[$cursor + 2]) << 8)
            | ord($bytes[$cursor + 3]);
    }

    private function readString(string $bytes, int &$cursor, int $listEnd): ?string
    {
        $stringConstructor = ord($bytes[$cursor]);
        ++$cursor;

        if ($stringConstructor === self::CONSTRUCTOR_STR8) {
            if ($cursor >= $listEnd) {
                throw PerformativeException::truncatedOpen();
            }

            $length = ord($bytes[$cursor]);
            ++$cursor;
        } elseif ($stringConstructor === self::CONSTRUCTOR_STR32) {
            if ($cursor + 4 > $listEnd) {
                throw PerformativeException::truncatedOpen();
            }

            $length = $this->readUInt32($bytes, $cursor);
            $cursor += 4;
        } else {
            return null;
        }

        if ($cursor + $length > $listEnd) {
            throw PerformativeException::truncatedOpen();
        }

        $value = substr($bytes, $cursor, $length);
        $cursor += $length;

        return $value;
    }
}
