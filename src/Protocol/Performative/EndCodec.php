<?php

declare(strict_types=1);

namespace Sigbits\Amqp\Protocol\Performative;

final class EndCodec
{
    private const string DESCRIPTOR = "\x00\x53\x17";
    private const int DESCRIPTOR_LENGTH = 3;
    private const int CONSTRUCTOR_LIST0 = 0x45;

    public function encode(End $end): string
    {
        return self::DESCRIPTOR . chr(self::CONSTRUCTOR_LIST0);
    }

    public function decode(string $bytes): End
    {
        if (strlen($bytes) < self::DESCRIPTOR_LENGTH + 1) {
            throw PerformativeException::truncatedEnd();
        }

        if (substr($bytes, 0, self::DESCRIPTOR_LENGTH) !== self::DESCRIPTOR) {
            throw PerformativeException::expectedEndDescriptor();
        }

        if (ord($bytes[self::DESCRIPTOR_LENGTH]) !== self::CONSTRUCTOR_LIST0) {
            throw PerformativeException::endErrorPayloadUnsupported();
        }

        return new End();
    }
}
