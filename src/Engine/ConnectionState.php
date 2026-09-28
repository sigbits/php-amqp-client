<?php

declare(strict_types=1);

namespace Sigbits\Amqp\Engine;

enum ConnectionState
{
    case Idle;
    case OpenSent;
    case Opened;
    case CloseSent;
    case Closed;
}
