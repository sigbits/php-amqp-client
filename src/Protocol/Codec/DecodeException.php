<?php

declare(strict_types=1);

namespace Sigbits\Amqp\Protocol\Codec;

use RuntimeException;

final class DecodeException extends RuntimeException
{
    public static function unsupportedFormatCode(int $formatCode): self
    {
        return new self(sprintf(
            'Unsupported AMQP format code 0x%02X.',
            $formatCode,
        ));
    }

    public static function truncatedBoolean(): self
    {
        return new self('Truncated AMQP boolean value.');
    }
}
