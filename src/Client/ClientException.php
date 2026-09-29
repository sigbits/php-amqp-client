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

    public static function receiverLinkDetached(): self
    {
        return new self('Cannot receive from detached AMQP receiver link.');
    }
}
