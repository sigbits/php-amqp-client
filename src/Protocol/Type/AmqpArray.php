<?php

declare(strict_types=1);

namespace Sigbits\Amqp\Protocol\Type;

use InvalidArgumentException;

final readonly class AmqpArray
{
    /**
     * @var list<mixed>
     */
    public array $items;

    /**
     * @param array<array-key, mixed> $items
     */
    public function __construct(
        public string $elementConstructor,
        array $items,
    ) {
        if ($this->elementConstructor === '') {
            throw new InvalidArgumentException('AMQP array element constructor must not be empty.');
        }

        if (!array_is_list($items)) {
            throw new InvalidArgumentException('AMQP array items must be a list array.');
        }

        $this->items = $items;
    }
}
