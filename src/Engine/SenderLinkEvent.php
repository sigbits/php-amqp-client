<?php

declare(strict_types=1);

namespace Sigbits\Amqp\Engine;

enum SenderLinkEvent
{
    case LinkAttached;
    case LinkDetached;
}
