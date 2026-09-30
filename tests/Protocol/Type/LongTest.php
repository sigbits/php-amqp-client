<?php

declare(strict_types=1);

namespace Sigbits\Amqp\Tests\Protocol\Type;

use PHPUnit\Framework\TestCase;
use Sigbits\Amqp\Protocol\Type\Long_;

final class LongTest extends TestCase
{
    public function testAcceptsMinimumValue(): void
    {
        $value = new Long_(PHP_INT_MIN);

        self::assertSame(PHP_INT_MIN, $value->value);
    }

    public function testAcceptsMaximumValue(): void
    {
        $value = new Long_(PHP_INT_MAX);

        self::assertSame(PHP_INT_MAX, $value->value);
    }
}
