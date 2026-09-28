<?php

declare(strict_types=1);

namespace Sigbits\Amqp\Protocol\Performative;

use InvalidArgumentException;

final readonly class Open
{
    public function __construct(
        public string $containerId,
    ) {
        if ($containerId === '') {
            throw new InvalidArgumentException('AMQP open container-id must not be empty.');
        }
    }
}
