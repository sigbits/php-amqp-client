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
}
