<?php

declare(strict_types=1);

namespace Sigbits\Amqp\Tests\Protocol\Type;

use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use Sigbits\Amqp\Protocol\Type\AmqpList;
use Sigbits\Amqp\Protocol\Type\UInt;

final class AmqpListTest extends TestCase
{
    public function testStoresOrderedItems(): void
    {
        $list = new AmqpList(['orders', new UInt(42), true]);

        self::assertEquals(['orders', new UInt(42), true], $list->items);
    }

    public function testRejectsAssociativeArray(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('AMQP list items must be a list array.');

        new AmqpList(['key' => 'value']);
    }
}
