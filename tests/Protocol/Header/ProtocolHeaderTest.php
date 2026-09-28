<?php

declare(strict_types=1);

namespace Sigbits\Amqp\Tests\Protocol\Header;

use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use Sigbits\Amqp\Protocol\Header\ProtocolHeader;

final class ProtocolHeaderTest extends TestCase
{
    public function testRejectsByteBelowMinimum(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('AMQP protocol header bytes must be between 0 and 255.');

        new ProtocolHeader(-1, 1, 0, 0);
    }

    public function testRejectsByteAboveMaximum(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('AMQP protocol header bytes must be between 0 and 255.');

        new ProtocolHeader(256, 1, 0, 0);
    }
}
