<?php

declare(strict_types=1);

namespace Sigbits\Amqp\Tests\Protocol\Type;

use PHPUnit\Framework\TestCase;
use Sigbits\Amqp\Protocol\Type\Float64;

final class Float64Test extends TestCase
{
    public function testStoresValue(): void
    {
        $value = new Float64(1.5);

        self::assertSame(1.5, $value->value);
    }
}
