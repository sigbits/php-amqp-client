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
}
