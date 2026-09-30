<?php

declare(strict_types=1);

namespace Sigbits\Amqp\Client;

use RuntimeException;
use Sigbits\Amqp\Protocol\Performative\PerformativeError;

final class ClientException extends RuntimeException
{
    public static function senderLinkDetached(): self
    {
        return new self('Cannot send on detached AMQP sender link.');
    }

    public static function senderLinkDetachedDuringOpen(?PerformativeError $error = null): self
    {
        return new self(self::withErrorDetails(
            'Cannot open AMQP sender link because the remote peer detached it.',
            $error,
        ));
    }

    public static function receiverLinkDetached(): self
    {
        return new self('Cannot receive from detached AMQP receiver link.');
    }

    public static function receiverLinkDetachedDuringSettlement(): self
    {
        return new self('Cannot settle AMQP delivery because the receiver link is detached.');
    }

    public static function receiverLinkDetachedDuringOpen(?PerformativeError $error = null): self
    {
        return new self(self::withErrorDetails(
            'Cannot open AMQP receiver link because the remote peer detached it.',
            $error,
        ));
    }

    public static function remoteConnectionClosed(): self
    {
        return new self('AMQP connection was closed by the remote peer.');
    }

    public static function remoteSessionEnded(): self
    {
        return new self('AMQP session was ended by the remote peer.');
    }

    public static function deliveryAlreadySettled(): self
    {
        return new self('Cannot settle AMQP delivery because it is already settled.');
    }

    private static function withErrorDetails(string $message, ?PerformativeError $error): string
    {
        if ($error === null) {
            return $message;
        }

        $message = rtrim($message, '.');

        if ($error->description === null) {
            return $message . ': ' . $error->condition;
        }

        return $message . ': ' . $error->condition . ' - ' . $error->description;
    }
}
