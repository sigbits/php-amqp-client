<?php

declare(strict_types=1);

namespace Sigbits\Amqp\Protocol\Type;

use InvalidArgumentException;

final readonly class Uuid
{
    public string $value;

    public function __construct(string $value)
    {
        if (!preg_match('/\A[0-9a-fA-F]{8}-[0-9a-fA-F]{4}-[0-9a-fA-F]{4}-[0-9a-fA-F]{4}-[0-9a-fA-F]{12}\z/', $value)) {
            throw new InvalidArgumentException('AMQP uuid value must use canonical RFC 4122 text form.');
        }

        $this->value = strtolower($value);
    }
}
