<?php

declare(strict_types=1);

namespace Sigbits\Amqp\Tests\Protocol\Type;

use PHPUnit\Framework\TestCase;
use Sigbits\Amqp\Protocol\Type\Binary;

final class BinaryTest extends TestCase
{
    public function testStoresBytes(): void
    {
        $value = new Binary("\x00orders\xff");

        self::assertSame("\x00orders\xff", $value->value);
    }
}
