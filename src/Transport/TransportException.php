<?php

declare(strict_types=1);

namespace Sigbits\Amqp\Transport;

use RuntimeException;

final class TransportException extends RuntimeException
{
    public static function unsupportedScheme(): self
    {
        return new self('Unsupported AMQP connection URI scheme.');
    }

    public static function missingHost(): self
    {
        return new self('AMQP connection URI requires a host.');
    }
}
