<?php

declare(strict_types=1);

namespace Sigbits\Amqp\Tests\Protocol\Type;

use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use Sigbits\Amqp\Protocol\Type\AmqpDescribedValue;
use Sigbits\Amqp\Protocol\Type\Symbol;
use Sigbits\Amqp\Protocol\Type\UInt;
use Sigbits\Amqp\Protocol\Type\ULong;

final class AmqpDescribedValueTest extends TestCase
{
    public function testStoresDescriptorAndValue(): void
    {
        $value = new AmqpDescribedValue(new ULong(117), 'body');

        self::assertEquals(new ULong(117), $value->descriptor);
        self::assertSame('body', $value->value);
    }

    public function testAcceptsSymbolDescriptor(): void
    {
        $value = new AmqpDescribedValue(new Symbol('example:state'), true);

        self::assertEquals(new Symbol('example:state'), $value->descriptor);
        self::assertTrue($value->value);
    }

    public function testRejectsNonDescriptorValue(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('AMQP described value descriptor must be an ulong or symbol.');

        new AmqpDescribedValue(new UInt(1), true);
    }
}
