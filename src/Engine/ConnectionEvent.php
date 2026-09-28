<?php

declare(strict_types=1);

namespace Sigbits\Amqp\Engine;

enum ConnectionEvent
{
    case ConnectionOpened;
    case ConnectionClosed;
}
