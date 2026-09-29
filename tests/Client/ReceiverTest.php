<?php

declare(strict_types=1);

namespace Sigbits\Amqp\Tests\Client;

use PHPUnit\Framework\TestCase;
use Sigbits\Amqp\Client\Receiver;
use Sigbits\Amqp\Engine\ReceiverLinkEngine;
use Sigbits\Amqp\Protocol\Message\Message;

final class ReceiverTest extends TestCase
{
    public function testReceiveReturnsQueuedMessageWithoutReading(): void
    {
        $link = $this->attachedReceiverLink();
        $link->push(
            "\x00\x00\x00\x35\x02\x00\x00\x01"
            . "\x00\x53\x14\xc0\x1e\x06"
            . "\x70\x00\x00\x00\x00"
            . "\x70\x00\x00\x00\x00"
            . "\xa0\x0adelivery-0"
            . "\x70\x00\x00\x00\x00"
            . "\x40"
            . "\x42"
            . "\x00\x53\x75\xa0\x05hello",
        );
        $readCalled = false;
        $receiver = new Receiver($link, static function () use (&$readCalled): ?string {
            $readCalled = true;

            return null;
        });

        self::assertEquals(new Message(body: 'hello'), $receiver->receive(timeoutMilliseconds: 0));
        self::assertFalse($readCalled);
    }

    public function testReceiveReadsUntilMessageArrives(): void
    {
        $receiver = new Receiver($this->attachedReceiverLink(), $this->reader([
            "\x00\x00\x00\x35\x02\x00\x00\x01"
            . "\x00\x53\x14\xc0\x1e\x06"
            . "\x70\x00\x00\x00\x00"
            . "\x70\x00\x00\x00\x00"
            . "\xa0\x0adelivery-0"
            . "\x70\x00\x00\x00\x00"
            . "\x40"
            . "\x42"
            . "\x00\x53\x75\xa0\x05hello",
        ]));

        self::assertEquals(new Message(body: 'hello'), $receiver->receive(timeoutMilliseconds: 100));
    }

    public function testReceiveIgnoresUnrelatedFramesWhileWaitingForMessage(): void
    {
        $receiver = new Receiver($this->attachedReceiverLink(), $this->reader([
            "\x00\x00\x00\x21\x02\x00\x00\x01"
            . "\x00\x53\x13\xc0\x14\x07"
            . "\x40\x40\x40\x40"
            . "\x70\x00\x00\x00\x00"
            . "\x70\x00\x00\x00\x00"
            . "\x70\x00\x00\x00\x01",
            "\x00\x00\x00\x35\x02\x00\x00\x01"
            . "\x00\x53\x14\xc0\x1e\x06"
            . "\x70\x00\x00\x00\x00"
            . "\x70\x00\x00\x00\x00"
            . "\xa0\x0adelivery-0"
            . "\x70\x00\x00\x00\x00"
            . "\x40"
            . "\x42"
            . "\x00\x53\x75\xa0\x05hello",
        ]));

        self::assertEquals(new Message(body: 'hello'), $receiver->receive(timeoutMilliseconds: 100));
    }

    public function testReceiveReturnsNullWhenTimeoutExpiresWithoutMessage(): void
    {
        $receiver = new Receiver($this->attachedReceiverLink(), static fn (): ?string => null);

        self::assertNull($receiver->receive(timeoutMilliseconds: 0));
    }

    public function testReceiveDeliveryCanAcceptReceivedMessage(): void
    {
        $written = [];
        $receiver = new Receiver(
            $this->attachedReceiverLink(),
            $this->reader([
                "\x00\x00\x00\x35\x02\x00\x00\x01"
                . "\x00\x53\x14\xc0\x1e\x06"
                . "\x70\x00\x00\x00\x03"
                . "\x70\x00\x00\x00\x03"
                . "\xa0\x0adelivery-3"
                . "\x70\x00\x00\x00\x00"
                . "\x40"
                . "\x42"
                . "\x00\x53\x75\xa0\x05hello",
            ]),
            static function (array $frames) use (&$written): void {
                array_push($written, ...$frames);
            },
        );

        $delivery = $receiver->receiveDelivery(timeoutMilliseconds: 100);

        self::assertNotNull($delivery);
        self::assertEquals(new Message(body: 'hello'), $delivery->message());

        $delivery->accept();

        self::assertSame([
            "\x00\x00\x00\x1a\x02\x00\x00\x01"
            . "\x00\x53\x15\xc0\x0d\x05"
            . "\x41"
            . "\x70\x00\x00\x00\x03"
            . "\x40"
            . "\x41"
            . "\x00\x53\x24\x45",
        ], $written);
    }

    public function testReceiveDeliveryCanReleaseReceivedMessage(): void
    {
        $written = [];
        $receiver = new Receiver(
            $this->attachedReceiverLink(),
            $this->reader([
                "\x00\x00\x00\x35\x02\x00\x00\x01"
                . "\x00\x53\x14\xc0\x1e\x06"
                . "\x70\x00\x00\x00\x04"
                . "\x70\x00\x00\x00\x04"
                . "\xa0\x0adelivery-4"
                . "\x70\x00\x00\x00\x00"
                . "\x40"
                . "\x42"
                . "\x00\x53\x75\xa0\x05hello",
            ]),
            static function (array $frames) use (&$written): void {
                array_push($written, ...$frames);
            },
        );

        $delivery = $receiver->receiveDelivery(timeoutMilliseconds: 100);

        self::assertNotNull($delivery);

        $delivery->release();

        self::assertSame([
            "\x00\x00\x00\x1a\x02\x00\x00\x01"
            . "\x00\x53\x15\xc0\x0d\x05"
            . "\x41"
            . "\x70\x00\x00\x00\x04"
            . "\x40"
            . "\x41"
            . "\x00\x53\x26\x45",
        ], $written);
    }

    public function testReceiveDeliveryCanRejectReceivedMessage(): void
    {
        $written = [];
        $receiver = new Receiver(
            $this->attachedReceiverLink(),
            $this->reader([
                "\x00\x00\x00\x35\x02\x00\x00\x01"
                . "\x00\x53\x14\xc0\x1e\x06"
                . "\x70\x00\x00\x00\x05"
                . "\x70\x00\x00\x00\x05"
                . "\xa0\x0adelivery-5"
                . "\x70\x00\x00\x00\x00"
                . "\x40"
                . "\x42"
                . "\x00\x53\x75\xa0\x05hello",
            ]),
            static function (array $frames) use (&$written): void {
                array_push($written, ...$frames);
            },
        );

        $delivery = $receiver->receiveDelivery(timeoutMilliseconds: 100);

        self::assertNotNull($delivery);

        $delivery->reject();

        self::assertSame([
            "\x00\x00\x00\x1a\x02\x00\x00\x01"
            . "\x00\x53\x15\xc0\x0d\x05"
            . "\x41"
            . "\x70\x00\x00\x00\x05"
            . "\x40"
            . "\x41"
            . "\x00\x53\x25\x45",
        ], $written);
    }

    private function attachedReceiverLink(): ReceiverLinkEngine
    {
        $engine = new ReceiverLinkEngine(sessionChannel: 1, name: 'receiver', handle: 0);
        $engine->attach();
        $engine->push(
            "\x00\x00\x00\x1e\x02\x00\x00\x01"
            . "\x00\x53\x12\xc0\x11\x03\xa1\x08receiver"
            . "\x70\x00\x00\x00\x00"
            . "\x42",
        );

        return $engine;
    }

    /**
     * @param list<string> $chunks
     */
    private function reader(array $chunks): callable
    {
        return static function () use (&$chunks): ?string {
            return array_shift($chunks);
        };
    }
}
