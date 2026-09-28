<?php

declare(strict_types=1);

namespace Sigbits\Amqp\Tests;

use PHPUnit\Framework\TestCase;

final class EnvironmentTest extends TestCase
{
    public function testProjectRequiresPhp83OrNewer(): void
    {
        self::assertGreaterThanOrEqual(80300, PHP_VERSION_ID);
    }
}
