<?php

declare(strict_types=1);

namespace Sigbits\Amqp\Tests\Protocol\Type;

use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use Sigbits\Amqp\Protocol\Type\Decimal64;

final class Decimal64Test extends TestCase
{
    public function testStoresWireBytes(): void
    {
        $value = new Decimal64("\x01\x02\x03\x04\x05\x06\x07\x08");

        self::assertSame("\x01\x02\x03\x04\x05\x06\x07\x08", $value->bytes);
    }

    public function testRejectsTooFewBytes(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('AMQP decimal64 value must contain exactly 8 bytes.');

        new Decimal64("\x01\x02\x03\x04\x05\x06\x07");
    }

    public function testRejectsTooManyBytes(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('AMQP decimal64 value must contain exactly 8 bytes.');

        new Decimal64("\x01\x02\x03\x04\x05\x06\x07\x08\x09");
    }
}
