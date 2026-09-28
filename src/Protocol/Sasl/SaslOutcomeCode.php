<?php

declare(strict_types=1);

namespace Sigbits\Amqp\Protocol\Sasl;

enum SaslOutcomeCode: int
{
    case Ok = 0;
    case Auth = 1;
    case Sys = 2;
    case SysPerm = 3;
    case SysTemp = 4;
}
