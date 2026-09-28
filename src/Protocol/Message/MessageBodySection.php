<?php

declare(strict_types=1);

namespace Sigbits\Amqp\Protocol\Message;

enum MessageBodySection
{
    case Data;
    case AmqpValue;
}
