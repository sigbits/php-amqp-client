<?php

declare(strict_types=1);

namespace Sigbits\Amqp\Tests\Protocol\Type;

use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use Sigbits\Amqp\Protocol\Type\Decimal32;

final class Decimal32Test extends TestCase
{
    public function testStoresWireBytes(): void
    {
        $value = new Decimal32("\x01\x02\x03\x04");

        self::assertSame("\x01\x02\x03\x04", $value->bytes);
    }

    public function testRejectsTooFewBytes(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('AMQP decimal32 value must contain exactly 4 bytes.');

        new Decimal32("\x01\x02\x03");
    }

    public function testRejectsTooManyBytes(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('AMQP decimal32 value must contain exactly 4 bytes.');

        new Decimal32("\x01\x02\x03\x04\x05");
    }
}
