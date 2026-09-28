<?php

declare(strict_types=1);

namespace Sigbits\Amqp\Protocol\Performative;

final class CloseCodec
{
    private const string DESCRIPTOR = "\x00\x53\x18";
    private const int DESCRIPTOR_LENGTH = 3;
    private const int CONSTRUCTOR_LIST0 = 0x45;

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

        if (ord($bytes[self::DESCRIPTOR_LENGTH]) !== self::CONSTRUCTOR_LIST0) {
            throw PerformativeException::closeErrorPayloadUnsupported();
        }

        return new Close();
    }
}
