<?php

declare(strict_types=1);

namespace Sigbits\Amqp\Tests\Protocol\Type;

use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use Sigbits\Amqp\Protocol\Type\ULong;

final class ULongTest extends TestCase
{
    public function testAcceptsIntegerMinimumValue(): void
    {
        $value = new ULong(0);

        self::assertSame('0', $value->value);
    }

    public function testAcceptsStringMaximumValue(): void
    {
        $value = new ULong('18446744073709551615');

        self::assertSame('18446744073709551615', $value->value);
    }

    public function testNormalizesLeadingZeroes(): void
    {
        $value = new ULong('00000042');

        self::assertSame('42', $value->value);
    }

    public function testRejectsNegativeInteger(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('AMQP ulong value must be between 0 and 18446744073709551615.');

        new ULong(-1);
    }

    public function testRejectsNonDecimalString(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('AMQP ulong value must be an unsigned decimal string.');

        new ULong('12x');
    }

    public function testRejectsValueAboveMaximum(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('AMQP ulong value must be between 0 and 18446744073709551615.');

        new ULong('18446744073709551616');
    }
}
