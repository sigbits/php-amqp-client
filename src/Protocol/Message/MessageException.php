<?php

declare(strict_types=1);

namespace Sigbits\Amqp\Protocol\Message;

use RuntimeException;

final class MessageException extends RuntimeException
{
    public static function expectedDataBodyDescriptor(): self
    {
        return new self('Expected AMQP data body section descriptor.');
    }

    public static function unsupportedDataBodyEncoding(): self
    {
        return new self('AMQP message body must use vbin8 data encoding.');
    }

    public static function truncatedDataBody(): self
    {
        return new self('Truncated AMQP data body section.');
    }

    public static function malformedHeader(): self
    {
        return new self('Malformed AMQP header section.');
    }

    public static function truncatedHeader(): self
    {
        return new self('Truncated AMQP header section.');
    }

    public static function malformedDeliveryAnnotations(): self
    {
        return new self('Malformed AMQP delivery annotations section.');
    }

    public static function truncatedDeliveryAnnotations(): self
    {
        return new self('Truncated AMQP delivery annotations section.');
    }

    public static function malformedMessageAnnotations(): self
    {
        return new self('Malformed AMQP message annotations section.');
    }

    public static function truncatedMessageAnnotations(): self
    {
        return new self('Truncated AMQP message annotations section.');
    }

    public static function malformedProperties(): self
    {
        return new self('Malformed AMQP properties section.');
    }

    public static function truncatedProperties(): self
    {
        return new self('Truncated AMQP properties section.');
    }

    public static function malformedApplicationProperties(): self
    {
        return new self('Malformed AMQP application properties section.');
    }

    public static function truncatedApplicationProperties(): self
    {
        return new self('Truncated AMQP application properties section.');
    }

    public static function malformedFooter(): self
    {
        return new self('Malformed AMQP footer section.');
    }

    public static function truncatedFooter(): self
    {
        return new self('Truncated AMQP footer section.');
    }
}
