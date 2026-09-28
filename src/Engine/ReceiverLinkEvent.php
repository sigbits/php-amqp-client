<?php

declare(strict_types=1);

namespace Sigbits\Amqp\Engine;

enum ReceiverLinkEvent
{
    case LinkAttached;
    case LinkDetached;
}
