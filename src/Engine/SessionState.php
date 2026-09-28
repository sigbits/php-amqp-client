<?php

declare(strict_types=1);

namespace Sigbits\Amqp\Engine;

enum SessionState
{
    case Idle;
    case BeginSent;
    case Mapped;
    case EndSent;
    case Ended;
}
