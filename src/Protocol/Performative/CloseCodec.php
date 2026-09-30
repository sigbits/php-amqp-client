<?php

declare(strict_types=1);

namespace Sigbits\Amqp\Protocol\Performative;

final class CloseCodec
{
    private const string DESCRIPTOR = "\x00\x53\x18";
    private const int DESCRIPTOR_LENGTH = 3;
    private const int CONSTRUCTOR_LIST0 = 0x45;
    private const int CONSTRUCTOR_LIST8 = 0xc0;

    public function encode(Close $close): string
    {
        return self::DESCRIPTOR . chr(self::CONSTRUCTOR_LIST0);
    }

    public function decode(string $bytes): Close
    {
        if (strlen($bytes) < self::DESCRIPTOR_LENGTH + 1) {
            throw PerformativeException::truncatedClose();
        }

        if (substr($bytes, 0, self::DESCRIPTOR_LENGTH) !== self::DESCRIPTOR) {
            throw PerformativeException::expectedCloseDescriptor();
        }

        $constructor = ord($bytes[self::DESCRIPTOR_LENGTH]);

        if ($constructor === self::CONSTRUCTOR_LIST0) {
            return new Close();
        }

        if ($constructor !== self::CONSTRUCTOR_LIST8) {
            throw PerformativeException::closeErrorPayloadUnsupported();
        }

        if (strlen($bytes) < self::DESCRIPTOR_LENGTH + 3) {
            throw PerformativeException::truncatedClose();
        }

        $listSize = ord($bytes[self::DESCRIPTOR_LENGTH + 1]);
        $fieldCount = ord($bytes[self::DESCRIPTOR_LENGTH + 2]);
        $listEnd = self::DESCRIPTOR_LENGTH + 3 + $listSize - 1;

        if (strlen($bytes) < $listEnd) {
            throw PerformativeException::truncatedClose();
        }

        if ($fieldCount > 1) {
            throw PerformativeException::closeErrorPayloadUnsupported();
        }

        return new Close();
    }
}
