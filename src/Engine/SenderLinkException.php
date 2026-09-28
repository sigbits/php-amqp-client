<?php

declare(strict_types=1);

namespace Sigbits\Amqp\Engine;

use RuntimeException;

final class SenderLinkException extends RuntimeException
{
    public static function cannotAttach(SenderLinkState $state): self
    {
        return new self(sprintf('Cannot attach AMQP sender link from state %s.', $state->name));
    }

    public static function cannotDetach(SenderLinkState $state): self
    {
        return new self(sprintf('Cannot detach AMQP sender link from state %s.', $state->name));
    }

    public static function noCreditAvailable(): self
    {
        return new self('Cannot claim AMQP sender link credit when none is available.');
    }

    public static function unsupportedSenderLinkPerformative(): self
    {
        return new self('Unsupported AMQP sender link performative.');
    }
}
