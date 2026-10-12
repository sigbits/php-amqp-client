<?php

declare(strict_types=1);

namespace Sigbits\Amqp\Tests\Protocol\Type;

use PHPUnit\Framework\TestCase;
use Sigbits\Amqp\Protocol\Type\Symbol;

final class SymbolTest extends TestCase
{
    public function testStoresSymbolText(): void
    {
        $value = new Symbol('amqp:accepted:list');

        self::assertSame('amqp:accepted:list', $value->value);
    }
}
