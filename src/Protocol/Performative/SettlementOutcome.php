<?php

declare(strict_types=1);

namespace Sigbits\Amqp\Protocol\Performative;

enum SettlementOutcome
{
    case Accepted;
    case Released;
    case Rejected;
}
