<?php

declare(strict_types=1);

namespace Sigbits\Amqp\Tests\Protocol\Performative;

use PHPUnit\Framework\TestCase;
use Sigbits\Amqp\Protocol\Performative\Disposition;
use Sigbits\Amqp\Protocol\Performative\DispositionCodec;
use Sigbits\Amqp\Protocol\Performative\PerformativeException;
use Sigbits\Amqp\Protocol\Performative\SettlementOutcome;

final class DispositionCodecTest extends TestCase
{
    public function testEncodesReceiverAcceptedDisposition(): void
    {
        $codec = new DispositionCodec();

        self::assertSame(
            "\x00\x53\x15\xc0\x0d\x05"
            . "\x41"
            . "\x70\x00\x00\x00\x00"
            . "\x40"
            . "\x41"
            . "\x00\x53\x24\x45",
            $codec->encode(new Disposition(
                deliveryId: 0,
                outcome: SettlementOutcome::Accepted,
            )),
        );
    }

    public function testEncodesReceiverReleasedDisposition(): void
    {
        $codec = new DispositionCodec();

        self::assertSame(
            "\x00\x53\x15\xc0\x0d\x05"
            . "\x41"
            . "\x70\x00\x00\x00\x01"
            . "\x40"
            . "\x41"
            . "\x00\x53\x26\x45",
            $codec->encode(new Disposition(
                deliveryId: 1,
                outcome: SettlementOutcome::Released,
            )),
        );
    }

    public function testEncodesReceiverRejectedDisposition(): void
    {
        $codec = new DispositionCodec();

        self::assertSame(
            "\x00\x53\x15\xc0\x0d\x05"
            . "\x41"
            . "\x70\x00\x00\x00\x02"
            . "\x40"
            . "\x41"
            . "\x00\x53\x25\x45",
            $codec->encode(new Disposition(
                deliveryId: 2,
                outcome: SettlementOutcome::Rejected,
            )),
        );
    }

    public function testDecodesReceiverAcceptedDispositionWithSmallUIntDeliveryId(): void
    {
        $codec = new DispositionCodec();

        self::assertEquals(
            new Disposition(
                deliveryId: 2,
                outcome: SettlementOutcome::Accepted,
            ),
            $codec->decode(
                "\x00\x53\x15\xc0\x0a\x05"
                . "\x41"
                . "\x52\x02"
                . "\x40"
                . "\x41"
                . "\x00\x53\x24\x45",
            ),
        );
    }

    public function testRejectsWrongDescriptor(): void
    {
        $codec = new DispositionCodec();

        $this->expectException(PerformativeException::class);
        $this->expectExceptionMessage('Expected AMQP disposition performative descriptor.');

        $codec->decode("\x00\x53\x14\x45");
    }

    public function testRejectsDispositionWithoutRequiredFields(): void
    {
        $codec = new DispositionCodec();

        $this->expectException(PerformativeException::class);
        $this->expectExceptionMessage('AMQP disposition performative requires role, first, settled, and state.');

        $codec->decode("\x00\x53\x15\x45");
    }
}
