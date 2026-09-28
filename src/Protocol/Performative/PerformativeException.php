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

    public static function expectedBeginDescriptor(): self
    {
        return new self('Expected AMQP begin performative descriptor.');
    }

    public static function missingBeginRequiredFields(): self
    {
        return new self('AMQP begin performative requires next-outgoing-id, incoming-window, and outgoing-window.');
    }

    public static function truncatedBegin(): self
    {
        return new self('Truncated AMQP begin performative.');
    }

    public static function expectedEndDescriptor(): self
    {
        return new self('Expected AMQP end performative descriptor.');
    }

    public static function truncatedEnd(): self
    {
        return new self('Truncated AMQP end performative.');
    }

    public static function endErrorPayloadUnsupported(): self
    {
        return new self('AMQP end error payload is not supported yet.');
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
