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

    public static function expectedAttachDescriptor(): self
    {
        return new self('Expected AMQP attach performative descriptor.');
    }

    public static function missingAttachRequiredFields(): self
    {
        return new self('AMQP attach performative requires name, handle, and role.');
    }

    public static function truncatedAttach(): self
    {
        return new self('Truncated AMQP attach performative.');
    }

    public static function expectedDetachDescriptor(): self
    {
        return new self('Expected AMQP detach performative descriptor.');
    }

    public static function expectedFlowDescriptor(): self
    {
        return new self('Expected AMQP flow performative descriptor.');
    }

    public static function missingFlowLinkCreditFields(): self
    {
        return new self('AMQP flow performative requires handle, delivery-count, and link-credit.');
    }

    public static function truncatedFlow(): self
    {
        return new self('Truncated AMQP flow performative.');
    }

    public static function missingDetachHandle(): self
    {
        return new self('AMQP detach performative requires handle.');
    }

    public static function truncatedDetach(): self
    {
        return new self('Truncated AMQP detach performative.');
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
