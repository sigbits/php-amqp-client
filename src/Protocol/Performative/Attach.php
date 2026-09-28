<?php

declare(strict_types=1);

namespace Sigbits\Amqp\Protocol\Performative;

use InvalidArgumentException;
use Sigbits\Amqp\Protocol\Type\UInt;

final readonly class Attach
{
    public function __construct(
        public string $name,
        public int $handle,
        public LinkRole $role,
    ) {
        if ($name === '') {
            throw new InvalidArgumentException('AMQP attach name must not be empty.');
        }

        if (strlen($name) > 255) {
            throw new InvalidArgumentException('AMQP attach name must fit in str8 encoding.');
        }

        new UInt($handle);
    }
}
