<?php

declare(strict_types=1);

namespace Sigbits\Amqp\Protocol\Performative;

use RuntimeException;

final class PerformativeException extends RuntimeException
{
    public static function expectedOpenDescriptor(): self
    {
        return new self('Expected AMQP open performative descriptor.');
    }

    public static function missingOpenContainerId(): self
    {
        return new self('AMQP open performative requires container-id.');
    }

    public static function truncatedOpen(): self
    {
        return new self('Truncated AMQP open performative.');
    }

    public static function expectedCloseDescriptor(): self
    {
        return new self('Expected AMQP close performative descriptor.');
    }

    public static function truncatedClose(): self
    {
        return new self('Truncated AMQP close performative.');
    }

    public static function closeErrorPayloadUnsupported(): self
    {
        return new self('AMQP close error payload is not supported yet.');
    }
}
