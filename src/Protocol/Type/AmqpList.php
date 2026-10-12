<?php

declare(strict_types=1);

namespace Sigbits\Amqp\Protocol\Type;

use InvalidArgumentException;

final readonly class AmqpList
{
    /**
     * @var list<mixed>
     */
    public array $items;

    /**
     * @param array<array-key, mixed> $items
     */
    public function __construct(
        array $items,
    ) {
        if (!array_is_list($items)) {
            throw new InvalidArgumentException('AMQP list items must be a list array.');
        }

        $this->items = $items;
    }
}
