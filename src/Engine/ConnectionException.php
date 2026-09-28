<?php

declare(strict_types=1);

namespace Sigbits\Amqp\Engine;

use RuntimeException;

final class ConnectionException extends RuntimeException
{
    public static function cannotStart(ConnectionState $state): self
    {
        return new self(sprintf('Cannot start AMQP connection from state %s.', $state->name));
    }

    public static function cannotClose(ConnectionState $state): self
    {
        return new self(sprintf('Cannot close AMQP connection from state %s.', $state->name));
    }

    public static function incompatibleRemoteProtocolHeader(): self
    {
        return new self('Remote peer did not negotiate AMQP 1.0.');
    }

    public static function unsupportedConnectionPerformative(): self
    {
        return new self('Unsupported AMQP connection performative.');
    }
}
