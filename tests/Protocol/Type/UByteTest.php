<?php

declare(strict_types=1);

namespace Sigbits\Amqp\Tests\Protocol\Type;

use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use Sigbits\Amqp\Protocol\Type\UByte;

final class UByteTest extends TestCase
{
    public function testAcceptsMinimumValue(): void
    {
        $value = new UByte(0);

        self::assertSame(0, $value->value);
    }

    public function testAcceptsMaximumValue(): void
    {
        $value = new UByte(255);

        self::assertSame(255, $value->value);
    }

    public function testRejectsValueBelowMinimum(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('AMQP ubyte value must be between 0 and 255.');

        new UByte(-1);
    }

    public function testRejectsValueAboveMaximum(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('AMQP ubyte value must be between 0 and 255.');

        new UByte(256);
    }
}
