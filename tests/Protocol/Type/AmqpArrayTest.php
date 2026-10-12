<?php

declare(strict_types=1);

namespace Sigbits\Amqp\Tests\Protocol\Type;

use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use Sigbits\Amqp\Protocol\Type\AmqpArray;
use Sigbits\Amqp\Protocol\Type\Symbol;

final class AmqpArrayTest extends TestCase
{
    public function testStoresElementConstructorAndItems(): void
    {
        $array = new AmqpArray("\xa3", [new Symbol('alpha'), new Symbol('beta')]);

        self::assertSame("\xa3", $array->elementConstructor);
        self::assertEquals([new Symbol('alpha'), new Symbol('beta')], $array->items);
    }

    public function testRejectsEmptyElementConstructor(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('AMQP array element constructor must not be empty.');

        new AmqpArray('', []);
    }

    public function testRejectsAssociativeItemList(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('AMQP array items must be a list array.');

        new AmqpArray("\xa3", ['key' => new Symbol('alpha')]);
    }
}
