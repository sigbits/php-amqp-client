<?php

declare(strict_types=1);

namespace Sigbits\Amqp\Protocol\Header;

use RuntimeException;

final class ProtocolHeaderException extends RuntimeException
{
    public static function truncated(): self
    {
        return new self('Truncated AMQP protocol header.');
    }

    public static function invalidMagic(): self
    {
        return new self('Invalid AMQP protocol header magic.');
    }
}
