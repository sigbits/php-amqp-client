<?php

declare(strict_types=1);

namespace Sigbits\Amqp\Tests\Protocol\Type;

use PHPUnit\Framework\TestCase;
use Sigbits\Amqp\Protocol\Type\Timestamp;

final class TimestampTest extends TestCase
{
    public function testStoresMillisecondsSinceUnixEpoch(): void
    {
        $value = new Timestamp(1700000000123);

        self::assertSame(1700000000123, $value->milliseconds);
    }
}
