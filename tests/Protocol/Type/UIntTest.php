<?php

declare(strict_types=1);

namespace Sigbits\Amqp\Tests\Protocol\Type;

use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use Sigbits\Amqp\Protocol\Type\UInt;

final class UIntTest extends TestCase
{
    public function testAcceptsMinimumValue(): void
    {
        $value = new UInt(0);

        self::assertSame(0, $value->value);
    }

    public function testAcceptsMaximumValue(): void
    {
        $value = new UInt(4294967295);

        self::assertSame(4294967295, $value->value);
    }

    public function testRejectsValueBelowMinimum(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('AMQP uint value must be between 0 and 4294967295.');

        new UInt(-1);
    }

    public function testRejectsValueAboveMaximum(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('AMQP uint value must be between 0 and 4294967295.');

        new UInt(4294967296);
    }
}
