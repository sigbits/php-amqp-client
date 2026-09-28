<?php

declare(strict_types=1);

namespace Sigbits\Amqp\Tests\Protocol\Type;

use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use Sigbits\Amqp\Protocol\Type\UShort;

final class UShortTest extends TestCase
{
    public function testAcceptsMinimumValue(): void
    {
        $value = new UShort(0);

        self::assertSame(0, $value->value);
    }

    public function testAcceptsMaximumValue(): void
    {
        $value = new UShort(65535);

        self::assertSame(65535, $value->value);
    }

    public function testRejectsValueBelowMinimum(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('AMQP ushort value must be between 0 and 65535.');

        new UShort(-1);
    }

    public function testRejectsValueAboveMaximum(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('AMQP ushort value must be between 0 and 65535.');

        new UShort(65536);
    }
}
