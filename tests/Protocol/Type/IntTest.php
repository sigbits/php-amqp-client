<?php

declare(strict_types=1);

namespace Sigbits\Amqp\Tests\Protocol\Type;

use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use Sigbits\Amqp\Protocol\Type\Int_;

final class IntTest extends TestCase
{
    public function testAcceptsMinimumValue(): void
    {
        $value = new Int_(-2147483648);

        self::assertSame(-2147483648, $value->value);
    }

    public function testAcceptsMaximumValue(): void
    {
        $value = new Int_(2147483647);

        self::assertSame(2147483647, $value->value);
    }

    public function testRejectsValueBelowMinimum(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('AMQP int value must be between -2147483648 and 2147483647.');

        new Int_(-2147483649);
    }

    public function testRejectsValueAboveMaximum(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('AMQP int value must be between -2147483648 and 2147483647.');

        new Int_(2147483648);
    }
}
