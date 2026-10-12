<?php

declare(strict_types=1);

namespace Sigbits\Amqp\Tests\Protocol\Type;

use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use Sigbits\Amqp\Protocol\Type\Char;

final class CharTest extends TestCase
{
    public function testStoresCodePoint(): void
    {
        $value = new Char(0x1f600);

        self::assertSame(0x1f600, $value->codePoint);
    }

    public function testAcceptsMaximumUnicodeScalarValue(): void
    {
        $value = new Char(0x10ffff);

        self::assertSame(0x10ffff, $value->codePoint);
    }

    public function testRejectsNegativeCodePoint(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('AMQP char value must be a Unicode scalar value between U+0000 and U+10FFFF.');

        new Char(-1);
    }

    public function testRejectsSurrogateCodePoint(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('AMQP char value must be a Unicode scalar value between U+0000 and U+10FFFF.');

        new Char(0xd800);
    }

    public function testRejectsCodePointAboveUnicodeRange(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('AMQP char value must be a Unicode scalar value between U+0000 and U+10FFFF.');

        new Char(0x110000);
    }
}
