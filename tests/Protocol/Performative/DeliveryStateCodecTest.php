<?php

declare(strict_types=1);

namespace Sigbits\Amqp\Tests\Protocol\Performative;

use PHPUnit\Framework\TestCase;
use Sigbits\Amqp\Protocol\Performative\Accepted;
use Sigbits\Amqp\Protocol\Performative\DeliveryStateCodec;
use Sigbits\Amqp\Protocol\Performative\Rejected;
use Sigbits\Amqp\Protocol\Performative\Released;

final class DeliveryStateCodecTest extends TestCase
{
    public function testEncodesAcceptedDeliveryState(): void
    {
        $codec = new DeliveryStateCodec();

        self::assertSame("\x00\x53\x24\x45", $codec->encode(new Accepted()));
    }

    public function testEncodesRejectedDeliveryState(): void
    {
        $codec = new DeliveryStateCodec();

        self::assertSame("\x00\x53\x25\x45", $codec->encode(new Rejected()));
    }

    public function testEncodesReleasedDeliveryState(): void
    {
        $codec = new DeliveryStateCodec();

        self::assertSame("\x00\x53\x26\x45", $codec->encode(new Released()));
    }

    public function testDecodesAcceptedDeliveryState(): void
    {
        $codec = new DeliveryStateCodec();

        self::assertEquals(new Accepted(), $codec->decode("\x00\x53\x24\x45"));
    }

    public function testDecodesRejectedDeliveryState(): void
    {
        $codec = new DeliveryStateCodec();

        self::assertEquals(new Rejected(), $codec->decode("\x00\x53\x25\x45"));
    }

    public function testDecodesReleasedDeliveryState(): void
    {
        $codec = new DeliveryStateCodec();

        self::assertEquals(new Released(), $codec->decode("\x00\x53\x26\x45"));
    }

    public function testDecodesDeliveryStateWithConsumedBytes(): void
    {
        $codec = new DeliveryStateCodec();

        self::assertEquals([new Accepted(), 4], $codec->decodeWithConsumed("\x00\x53\x24\x45\x40"));
    }
}
