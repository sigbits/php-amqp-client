<?php

declare(strict_types=1);

namespace Sigbits\Amqp\Tests\Protocol\Type;

use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use Sigbits\Amqp\Protocol\Type\Byte;

final class ByteTest extends TestCase
{
    public function testAcceptsMinimumValue(): void
    {
        $value = new Byte(-128);

        self::assertSame(-128, $value->value);
    }

    public function testAcceptsMaximumValue(): void
    {
        $value = new Byte(127);

        self::assertSame(127, $value->value);
    }

    public function testRejectsValueBelowMinimum(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('AMQP byte value must be between -128 and 127.');

        new Byte(-129);
    }

    public function testRejectsValueAboveMaximum(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('AMQP byte value must be between -128 and 127.');

        new Byte(128);
    }
}
