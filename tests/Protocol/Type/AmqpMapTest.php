<?php

declare(strict_types=1);

namespace Sigbits\Amqp\Tests\Protocol\Type;

use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use Sigbits\Amqp\Protocol\Type\AmqpMap;
use Sigbits\Amqp\Protocol\Type\Symbol;
use Sigbits\Amqp\Protocol\Type\UInt;

final class AmqpMapTest extends TestCase
{
    public function testStoresOrderedPairs(): void
    {
        $map = new AmqpMap([
            ['queue', 'orders'],
            [new Symbol('priority'), new UInt(5)],
        ]);

        self::assertEquals([
            ['queue', 'orders'],
            [new Symbol('priority'), new UInt(5)],
        ], $map->pairs);
    }

    public function testRejectsAssociativePairList(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('AMQP map pairs must be a list array.');

        new AmqpMap(['key' => ['value', true]]);
    }

    public function testRejectsInvalidPairShape(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('AMQP map pairs must contain exactly two ordered items.');

        new AmqpMap([['key']]);
    }
}
