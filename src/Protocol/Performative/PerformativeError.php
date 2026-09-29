<?php

declare(strict_types=1);

namespace Sigbits\Amqp\Protocol\Performative;

use InvalidArgumentException;

final readonly class PerformativeError
{
    public function __construct(
        public string $condition,
        public ?string $description = null,
    ) {
        if ($condition === '') {
            throw new InvalidArgumentException('AMQP error condition must not be empty.');
        }
    }
}
