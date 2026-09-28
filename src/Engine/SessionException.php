<?php

declare(strict_types=1);

namespace Sigbits\Amqp\Engine;

use RuntimeException;

final class SessionException extends RuntimeException
{
    public static function cannotBegin(SessionState $state): self
    {
        return new self(sprintf('Cannot begin AMQP session from state %s.', $state->name));
    }

    public static function cannotEnd(SessionState $state): self
    {
        return new self(sprintf('Cannot end AMQP session from state %s.', $state->name));
    }

    public static function unsupportedSessionPerformative(): self
    {
        return new self('Unsupported AMQP session performative.');
    }
}
