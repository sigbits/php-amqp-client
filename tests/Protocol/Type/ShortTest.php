<?php

declare(strict_types=1);

namespace Sigbits\Amqp\Tests\Protocol\Type;

use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use Sigbits\Amqp\Protocol\Type\Short;

final class ShortTest extends TestCase
{
    public function testAcceptsMinimumValue(): void
    {
        $value = new Short(-32768);

        self::assertSame(-32768, $value->value);
    }

    public function testAcceptsMaximumValue(): void
    {
        $value = new Short(32767);

        self::assertSame(32767, $value->value);
    }

    public function testRejectsValueBelowMinimum(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('AMQP short value must be between -32768 and 32767.');

        new Short(-32769);
    }

    public function testRejectsValueAboveMaximum(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('AMQP short value must be between -32768 and 32767.');

        new Short(32768);
    }
}
