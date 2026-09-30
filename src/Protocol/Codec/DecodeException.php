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

    public static function truncatedUByte(): self
    {
        return new self('Truncated AMQP ubyte value.');
    }

    public static function truncatedByte(): self
    {
        return new self('Truncated AMQP byte value.');
    }

    public static function truncatedUShort(): self
    {
        return new self('Truncated AMQP ushort value.');
    }

    public static function truncatedShort(): self
    {
        return new self('Truncated AMQP short value.');
    }

    public static function truncatedUInt(): self
    {
        return new self('Truncated AMQP uint value.');
    }

    public static function truncatedInt(): self
    {
        return new self('Truncated AMQP int value.');
    }

    public static function truncatedLong(): self
    {
        return new self('Truncated AMQP long value.');
    }

    public static function truncatedValue(): self
    {
        return new self('Truncated AMQP value.');
    }
}
