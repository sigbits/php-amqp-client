<?php

declare(strict_types=1);

namespace Sigbits\Amqp\Tests\Protocol\Type;

use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use Sigbits\Amqp\Protocol\Type\Decimal128;

final class Decimal128Test extends TestCase
{
    public function testStoresWireBytes(): void
    {
        $bytes = "\x00\x11\x22\x33\x44\x55\x66\x77\x88\x99\xaa\xbb\xcc\xdd\xee\xff";
        $value = new Decimal128($bytes);

        self::assertSame($bytes, $value->bytes);
    }

    public function testRejectsTooFewBytes(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('AMQP decimal128 value must contain exactly 16 bytes.');

        new Decimal128("\x00\x11\x22\x33\x44\x55\x66\x77\x88\x99\xaa\xbb\xcc\xdd\xee");
    }

    public function testRejectsTooManyBytes(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('AMQP decimal128 value must contain exactly 16 bytes.');

        new Decimal128("\x00\x11\x22\x33\x44\x55\x66\x77\x88\x99\xaa\xbb\xcc\xdd\xee\xff\x00");
    }
}
