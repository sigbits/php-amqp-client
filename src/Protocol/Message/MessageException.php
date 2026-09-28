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
}
