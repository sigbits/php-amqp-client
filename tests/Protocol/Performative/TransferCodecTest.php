<?php

declare(strict_types=1);

namespace Sigbits\Amqp\Tests\Protocol\Performative;

use PHPUnit\Framework\TestCase;
use Sigbits\Amqp\Protocol\Performative\PerformativeException;
use Sigbits\Amqp\Protocol\Performative\Transfer;
use Sigbits\Amqp\Protocol\Performative\TransferCodec;

final class TransferCodecTest extends TestCase
{
    public function testEncodesTransferPerformative(): void
    {
        $codec = new TransferCodec();

        self::assertSame(
            "\x00\x53\x14\xc0\x19\x06"
            . "\x70\x00\x00\x00\x00"
            . "\x70\x00\x00\x00\x00"
            . "\xa0\x05tag-0"
            . "\x70\x00\x00\x00\x00"
            . "\x40"
            . "\x42",
            $codec->encode(new Transfer(
                handle: 0,
                deliveryId: 0,
                deliveryTag: 'tag-0',
                messageFormat: 0,
                more: false,
            )),
        );
    }

    public function testDecodesTransferPerformative(): void
    {
        $codec = new TransferCodec();

        self::assertEquals(
            new Transfer(
                handle: 0,
                deliveryId: 0,
                deliveryTag: 'tag-0',
                messageFormat: 0,
                more: false,
            ),
            $codec->decode(
                "\x00\x53\x14\xc0\x19\x06"
                . "\x70\x00\x00\x00\x00"
                . "\x70\x00\x00\x00\x00"
                . "\xa0\x05tag-0"
                . "\x70\x00\x00\x00\x00"
                . "\x40"
                . "\x42",
            ),
        );
    }

    public function testDecodesBrokerTransferWithCompactIntegersAndOmittedMoreField(): void
    {
        $codec = new TransferCodec();

        self::assertEquals(
            new Transfer(
                handle: 0,
                deliveryId: 0,
                deliveryTag: "\x00",
                messageFormat: 0,
                more: false,
            ),
            $codec->decode(
                "\x00\x53\x14\xc0\x08\x05"
                . "\x43"
                . "\x43"
                . "\xa0\x01\x00"
                . "\x43"
                . "\x42",
            ),
        );
    }

    public function testEncodesTransferWithMoreFlag(): void
    {
        $codec = new TransferCodec();

        self::assertStringEndsWith(
            "\x40\x41",
            $codec->encode(new Transfer(
                handle: 0,
                deliveryId: 0,
                deliveryTag: 'tag-0',
                messageFormat: 0,
                more: true,
            )),
        );
    }

    public function testRejectsWrongDescriptor(): void
    {
        $codec = new TransferCodec();

        $this->expectException(PerformativeException::class);
        $this->expectExceptionMessage('Expected AMQP transfer performative descriptor.');

        $codec->decode("\x00\x53\x13\x45");
    }

    public function testRejectsTransferWithoutRequiredFields(): void
    {
        $codec = new TransferCodec();

        $this->expectException(PerformativeException::class);
        $this->expectExceptionMessage('AMQP transfer performative requires handle, delivery-id, delivery-tag, and message-format.');

        $codec->decode("\x00\x53\x14\x45");
    }

    public function testRejectsTruncatedTransfer(): void
    {
        $codec = new TransferCodec();

        $this->expectException(PerformativeException::class);
        $this->expectExceptionMessage('Truncated AMQP transfer performative.');

        $codec->decode(
            "\x00\x53\x14\xc0\x19\x06"
            . "\x70\x00\x00\x00\x00"
            . "\x70\x00\x00\x00\x00"
            . "\xa0\x05tag",
        );
    }

    public function testRejectsTransferWithUnconsumedListPayload(): void
    {
        $codec = new TransferCodec();

        $this->expectException(PerformativeException::class);
        $this->expectExceptionMessage('Malformed AMQP transfer performative.');

        $codec->decode(
            "\x00\x53\x14\xc0\x18\x04"
            . "\x70\x00\x00\x00\x00"
            . "\x70\x00\x00\x00\x00"
            . "\xa0\x05tag-0"
            . "\x70\x00\x00\x00\x00"
            . "\x40",
        );
    }
}
