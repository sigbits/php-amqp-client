<?php

declare(strict_types=1);

namespace Sigbits\Amqp\Protocol\Performative;

final class EndCodec
{
    private const string DESCRIPTOR = "\x00\x53\x17";
    private const int DESCRIPTOR_LENGTH = 3;
    private const int CONSTRUCTOR_LIST0 = 0x45;
    private const int CONSTRUCTOR_LIST8 = 0xc0;
    private const int CONSTRUCTOR_NULL = 0x40;

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

        $constructor = ord($bytes[self::DESCRIPTOR_LENGTH]);

        if ($constructor === self::CONSTRUCTOR_LIST0) {
            return new End();
        }

        if ($constructor === self::CONSTRUCTOR_LIST8) {
            if (strlen($bytes) < self::DESCRIPTOR_LENGTH + 3) {
                throw PerformativeException::truncatedEnd();
            }

            $listSize = ord($bytes[self::DESCRIPTOR_LENGTH + 1]);
            $fieldCount = ord($bytes[self::DESCRIPTOR_LENGTH + 2]);

            if ($listSize === 1 && $fieldCount === 0) {
                return new End();
            }

            if (
                $listSize === 2
                && $fieldCount === 1
                && strlen($bytes) >= self::DESCRIPTOR_LENGTH + 4
                && ord($bytes[self::DESCRIPTOR_LENGTH + 3]) === self::CONSTRUCTOR_NULL
            ) {
                return new End();
            }

            throw PerformativeException::endErrorPayloadUnsupported();
        }

        throw PerformativeException::endErrorPayloadUnsupported();
    }
}
