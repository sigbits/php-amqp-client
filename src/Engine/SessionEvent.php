<?php

declare(strict_types=1);

namespace Sigbits\Amqp\Engine;

enum SessionEvent
{
    case SessionMapped;
    case SessionEnded;
}
