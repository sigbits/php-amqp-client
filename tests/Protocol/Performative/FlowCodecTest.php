<?php

declare(strict_types=1);

namespace Sigbits\Amqp\Tests\Protocol\Performative;

use PHPUnit\Framework\TestCase;
use Sigbits\Amqp\Protocol\Performative\Flow;
use Sigbits\Amqp\Protocol\Performative\FlowCodec;
use Sigbits\Amqp\Protocol\Performative\PerformativeException;

final class FlowCodecTest extends TestCase
{
    public function testEncodesLinkCreditFlowPerformative(): void
    {
        $codec = new FlowCodec();

        self::assertSame(
            "\x00\x53\x13\xc0\x14\x07"
            . "\x40\x40\x40\x40"
            . "\x70\x00\x00\x00\x00"
            . "\x70\x00\x00\x00\x05"
            . "\x70\x00\x00\x00\x0a",
            $codec->encode(new Flow(
                handle: 0,
                deliveryCount: 5,
                linkCredit: 10,
            )),
        );
    }

    public function testEncodesIncomingWindowWhenPresent(): void
    {
        $codec = new FlowCodec();

        self::assertSame(
            "\x00\x53\x13\xc0\x20\x07"
            . "\x40"
            . "\x70\x7f\xff\xff\xff"
            . "\x70\x00\x00\x00\x00"
            . "\x70\x7f\xff\xff\xff"
            . "\x70\x00\x00\x00\x00"
            . "\x70\x00\x00\x00\x00"
            . "\x70\x00\x00\x00\x01",
            $codec->encode(new Flow(
                handle: 0,
                deliveryCount: 0,
                linkCredit: 1,
                incomingWindow: 2_147_483_647,
                nextOutgoingId: 0,
                outgoingWindow: 2_147_483_647,
            )),
        );
    }

    public function testDecodesLinkCreditFlowPerformative(): void
    {
        $codec = new FlowCodec();

        self::assertEquals(
            new Flow(
                handle: 0,
                deliveryCount: 5,
                linkCredit: 10,
            ),
            $codec->decode(
                "\x00\x53\x13\xc0\x14\x07"
                . "\x40\x40\x40\x40"
                . "\x70\x00\x00\x00\x00"
                . "\x70\x00\x00\x00\x05"
                . "\x70\x00\x00\x00\x0a",
            ),
        );
    }

    public function testDecodesBrokerFlowWithSessionFieldsAndCompactHandle(): void
    {
        $codec = new FlowCodec();

        self::assertEquals(
            new Flow(
                handle: 0,
                deliveryCount: 0,
                linkCredit: 1_000,
            ),
            $codec->decode(
                "\x00\x53\x13\xc0\x15\x07"
                . "\x43"
                . "\x70\x7f\xff\xff\xff"
                . "\x52\x01"
                . "\x70\x7f\xff\xff\xff"
                . "\x43"
                . "\x40"
                . "\x70\x00\x00\x03\xe8",
            ),
        );
    }

    public function testRejectsWrongDescriptor(): void
    {
        $codec = new FlowCodec();

        $this->expectException(PerformativeException::class);
        $this->expectExceptionMessage('Expected AMQP flow performative descriptor.');

        $codec->decode("\x00\x53\x12\x45");
    }

    public function testRejectsFlowWithoutLinkCreditFields(): void
    {
        $codec = new FlowCodec();

        $this->expectException(PerformativeException::class);
        $this->expectExceptionMessage('AMQP flow performative requires handle, delivery-count, and link-credit.');

        $codec->decode("\x00\x53\x13\x45");
    }

    public function testRejectsTruncatedFlow(): void
    {
        $codec = new FlowCodec();

        $this->expectException(PerformativeException::class);
        $this->expectExceptionMessage('Truncated AMQP flow performative.');

        $codec->decode(
            "\x00\x53\x13\xc0\x14\x07"
            . "\x40\x40\x40\x40"
            . "\x70\x00\x00\x00\x00"
            . "\x70\x00\x00\x00\x05",
        );
    }

    public function testRejectsFlowWithUnconsumedListPayload(): void
    {
        $codec = new FlowCodec();

        $this->expectException(PerformativeException::class);
        $this->expectExceptionMessage('Malformed AMQP flow performative.');

        $codec->decode(
            "\x00\x53\x13\xc0\x15\x07"
            . "\x40\x40\x40\x40"
            . "\x70\x00\x00\x00\x00"
            . "\x70\x00\x00\x00\x05"
            . "\x70\x00\x00\x00\x0a"
            . "\x40",
        );
    }
}
