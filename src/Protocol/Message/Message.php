<?php

declare(strict_types=1);

namespace Sigbits\Amqp\Protocol\Message;

use InvalidArgumentException;

final readonly class Message
{
    public function __construct(
        public string $body,
    ) {
        if (strlen($body) > 255) {
            throw new InvalidArgumentException('AMQP message body must fit in vbin8 data encoding.');
        }
    }
}
