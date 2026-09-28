<?php

declare(strict_types=1);

namespace Sigbits\Amqp\Engine;

enum SenderLinkState
{
    case Idle;
    case AttachSent;
    case Attached;
    case DetachSent;
    case Detached;
}
