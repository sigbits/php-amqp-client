<?php

declare(strict_types=1);

namespace Sigbits\Amqp\Engine;

use RuntimeException;

final class ReceiverLinkException extends RuntimeException
{
    public static function cannotAttach(ReceiverLinkState $state): self
    {
        return new self(sprintf('Cannot attach AMQP receiver link from state %s.', $state->name));
    }

    public static function cannotDetach(ReceiverLinkState $state): self
    {
        return new self(sprintf('Cannot detach AMQP receiver link from state %s.', $state->name));
    }

    public static function unsupportedReceiverLinkPerformative(): self
    {
        return new self('Unsupported AMQP receiver link performative.');
    }

    public static function truncatedTransferPayload(): self
    {
        return new self('Truncated AMQP receiver transfer payload.');
    }
}
