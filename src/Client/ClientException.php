<?php

declare(strict_types=1);

namespace Sigbits\Amqp\Client;

use RuntimeException;

final class ClientException extends RuntimeException
{
    public static function senderLinkDetached(): self
    {
        return new self('Cannot send on detached AMQP sender link.');
    }

    public static function senderLinkDetachedDuringOpen(): self
    {
        return new self('Cannot open AMQP sender link because the remote peer detached it.');
    }

    public static function receiverLinkDetached(): self
    {
        return new self('Cannot receive from detached AMQP receiver link.');
    }

    public static function receiverLinkDetachedDuringOpen(): self
    {
        return new self('Cannot open AMQP receiver link because the remote peer detached it.');
    }
}
