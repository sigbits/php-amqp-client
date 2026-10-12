<?php

declare(strict_types=1);

namespace Sigbits\Amqp\Protocol\Type;

use InvalidArgumentException;

final readonly class AmqpMap
{
    /**
     * @var list<array{0: mixed, 1: mixed}>
     */
    public array $pairs;

    /**
     * @param array<array-key, mixed> $pairs
     */
    public function __construct(
        array $pairs,
    ) {
        if (!array_is_list($pairs)) {
            throw new InvalidArgumentException('AMQP map pairs must be a list array.');
        }

        $normalized = [];

        foreach ($pairs as $pair) {
            if (!is_array($pair) || !array_is_list($pair) || count($pair) !== 2) {
                throw new InvalidArgumentException('AMQP map pairs must contain exactly two ordered items.');
            }

            $normalized[] = [$pair[0], $pair[1]];
        }

        $this->pairs = $normalized;
    }
}
