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

    public static function expectedTransferDescriptor(): self
    {
        return new self('Expected AMQP transfer performative descriptor.');
    }

    public static function expectedDispositionDescriptor(): self
    {
        return new self('Expected AMQP disposition performative descriptor.');
    }

    public static function missingDispositionRequiredFields(): self
    {
        return new self('AMQP disposition performative requires role, first, settled, and state.');
    }

    public static function malformedDisposition(): self
    {
        return new self('Malformed AMQP disposition performative.');
    }

    public static function truncatedDisposition(): self
    {
        return new self('Truncated AMQP disposition performative.');
    }

    public static function missingTransferRequiredFields(): self
    {
        return new self('AMQP transfer performative requires handle, delivery-id, delivery-tag, and message-format.');
    }

    public static function malformedTransfer(): self
    {
        return new self('Malformed AMQP transfer performative.');
    }

    public static function truncatedTransfer(): self
    {
        return new self('Truncated AMQP transfer performative.');
    }

    public static function missingFlowLinkCreditFields(): self
    {
        return new self('AMQP flow performative requires handle, delivery-count, and link-credit.');
    }

    public static function malformedFlow(): self
    {
        return new self('Malformed AMQP flow performative.');
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

    public static function malformedBegin(): self
    {
        return new self('Malformed AMQP begin performative.');
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
