<?php

declare(strict_types=1);

namespace Sigbits\Amqp\Protocol\Performative;

enum LinkRole
{
    case Sender;
    case Receiver;
}
