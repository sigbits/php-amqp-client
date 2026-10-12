<?php

declare(strict_types=1);

namespace Sigbits\Amqp\Tests\Protocol\Type;

use PHPUnit\Framework\TestCase;
use Sigbits\Amqp\Protocol\Type\Float32;

final class Float32Test extends TestCase
{
    public function testStoresValue(): void
    {
        $value = new Float32(1.5);

        self::assertSame(1.5, $value->value);
    }
}
