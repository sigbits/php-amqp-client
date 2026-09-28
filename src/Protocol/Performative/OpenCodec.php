<?php

declare(strict_types=1);

namespace Sigbits\Amqp\Protocol\Performative;

final class OpenCodec
{
    private const string DESCRIPTOR = "\x00\x53\x10";
    private const int DESCRIPTOR_LENGTH = 3;
    private const int CONSTRUCTOR_LIST0 = 0x45;
    private const int CONSTRUCTOR_LIST8 = 0xc0;
    private const int CONSTRUCTOR_STR8 = 0xa1;

    public function encode(Open $open): string
    {
        $containerIdLength = strlen($open->containerId);
        $listSize = 1 + 1 + 1 + $containerIdLength;

        return self::DESCRIPTOR
            . chr(self::CONSTRUCTOR_LIST8)
            . chr($listSize)
            . "\x01"
            . chr(self::CONSTRUCTOR_STR8)
            . chr($containerIdLength)
            . $open->containerId;
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

        if ($constructor !== self::CONSTRUCTOR_LIST8) {
            throw PerformativeException::truncatedOpen();
        }

        if (strlen($bytes) < $cursor + 2) {
            throw PerformativeException::truncatedOpen();
        }

        $listSize = ord($bytes[$cursor]);
        ++$cursor;
        $fieldCount = ord($bytes[$cursor]);
        ++$cursor;

        if ($fieldCount < 1) {
            throw PerformativeException::missingOpenContainerId();
        }

        $listEnd = $cursor + $listSize - 1;

        if (strlen($bytes) < $listEnd || $cursor >= $listEnd) {
            throw PerformativeException::truncatedOpen();
        }

        if (ord($bytes[$cursor]) !== self::CONSTRUCTOR_STR8) {
            throw PerformativeException::missingOpenContainerId();
        }

        ++$cursor;

        if ($cursor >= $listEnd) {
            throw PerformativeException::truncatedOpen();
        }

        $containerIdLength = ord($bytes[$cursor]);
        ++$cursor;

        if ($cursor + $containerIdLength > $listEnd) {
            throw PerformativeException::truncatedOpen();
        }

        return new Open(substr($bytes, $cursor, $containerIdLength));
    }
}
