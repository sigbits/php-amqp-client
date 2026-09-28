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

    public static function connectionFailed(string $target, string $reason): self
    {
        return new self(sprintf('Could not open AMQP stream %s: %s', $target, $reason));
    }

    public static function unexpectedEndOfStream(): self
    {
        return new self('Unexpected end of AMQP stream.');
    }

    public static function writeFailed(): self
    {
        return new self('Could not write complete AMQP stream payload.');
    }

    public static function unexpectedFrameType(int $type): self
    {
        return new self(sprintf('Unexpected AMQP frame type %d.', $type));
    }
}
