<?php

declare(strict_types=1);

namespace Sigbits\Amqp\Tests\Protocol\Performative;

use PHPUnit\Framework\TestCase;
use Sigbits\Amqp\Protocol\Performative\Open;
use Sigbits\Amqp\Protocol\Performative\OpenCodec;
use Sigbits\Amqp\Protocol\Performative\PerformativeException;
use Sigbits\Amqp\Protocol\Type\AmqpMap;
use Sigbits\Amqp\Protocol\Type\Symbol;
use Sigbits\Amqp\Protocol\Type\UInt;

final class OpenCodecTest extends TestCase
{
    public function testEncodesMinimalOpenPerformative(): void
    {
        $codec = new OpenCodec();

        self::assertSame(
            "\x00\x53\x10\xc0\x09\x01\xa1\x06client",
            $codec->encode(new Open(containerId: 'client')),
        );
    }

    public function testEncodesOpenPerformativeWithHostname(): void
    {
        $codec = new OpenCodec();

        self::assertSame(
            "\x00\x53\x10\xc0\x1e\x02\xa1\x06client\xa1\x13servicebus-emulator",
            $codec->encode(new Open(containerId: 'client', hostname: 'servicebus-emulator')),
        );
    }

    public function testEncodesOpenPerformativeWithOptionalFields(): void
    {
        $codec = new OpenCodec();

        self::assertSame(
            "\x00\x53\x10\xc0\x84\x0a"
            . "\xa1\x06client"
            . "\xa1\x06broker"
            . "\x70\x00\x01\x00\x00"
            . "\x60\x00\x2a"
            . "\x70\x00\x00\x75\x30"
            . "\xe0\x08\x01\xa3\x05en-US"
            . "\xe0\x0e\x02\xa3\x05en-US\x05nl-NL"
            . "\xe0\x12\x01\xa3\x0fANONYMOUS-RELAY"
            . "\xe0\x13\x01\xa3\x10DELAYED_DELIVERY"
            . "\xc1\x21\x04\xa3\x07product\xa1\x07sigbits\xa3\x07version\x70\x00\x00\x00\x0d",
            $codec->encode(new Open(
                containerId: 'client',
                hostname: 'broker',
                maxFrameSize: 65536,
                channelMax: 42,
                idleTimeOut: 30000,
                outgoingLocales: ['en-US'],
                incomingLocales: ['en-US', 'nl-NL'],
                offeredCapabilities: ['ANONYMOUS-RELAY'],
                desiredCapabilities: ['DELAYED_DELIVERY'],
                properties: new AmqpMap([
                    [new Symbol('product'), 'sigbits'],
                    [new Symbol('version'), new UInt(13)],
                ]),
            )),
        );
    }

    public function testDecodesMinimalOpenPerformative(): void
    {
        $codec = new OpenCodec();

        self::assertEquals(
            new Open(containerId: 'client'),
            $codec->decode("\x00\x53\x10\xc0\x09\x01\xa1\x06client"),
        );
    }

    public function testDecodesOpenPerformativeWithHostname(): void
    {
        $codec = new OpenCodec();

        self::assertEquals(
            new Open(containerId: 'client', hostname: 'servicebus-emulator'),
            $codec->decode("\x00\x53\x10\xc0\x1e\x02\xa1\x06client\xa1\x13servicebus-emulator"),
        );
    }

    public function testDecodesOpenPerformativeWithOptionalFields(): void
    {
        $codec = new OpenCodec();

        self::assertEquals(
            new Open(
                containerId: 'client',
                hostname: 'broker',
                maxFrameSize: 65536,
                channelMax: 42,
                idleTimeOut: 30000,
                outgoingLocales: ['en-US'],
                incomingLocales: ['en-US', 'nl-NL'],
                offeredCapabilities: ['ANONYMOUS-RELAY'],
                desiredCapabilities: ['DELAYED_DELIVERY'],
                properties: new AmqpMap([
                    [new Symbol('product'), 'sigbits'],
                    [new Symbol('version'), new UInt(13)],
                ]),
            ),
            $codec->decode(
                "\x00\x53\x10\xc0\x84\x0a"
                . "\xa1\x06client"
                . "\xa1\x06broker"
                . "\x70\x00\x01\x00\x00"
                . "\x60\x00\x2a"
                . "\x70\x00\x00\x75\x30"
                . "\xe0\x08\x01\xa3\x05en-US"
                . "\xe0\x0e\x02\xa3\x05en-US\x05nl-NL"
                . "\xe0\x12\x01\xa3\x0fANONYMOUS-RELAY"
                . "\xe0\x13\x01\xa3\x10DELAYED_DELIVERY"
                . "\xc1\x21\x04\xa3\x07product\xa1\x07sigbits\xa3\x07version\x70\x00\x00\x00\x0d",
            ),
        );
    }

    public function testDecodesOpenPerformativeMultipleFieldsFromSingleSymbols(): void
    {
        $codec = new OpenCodec();

        self::assertEquals(
            new Open(
                containerId: 'server',
                outgoingLocales: ['en-US'],
                incomingLocales: ['nl-NL'],
                offeredCapabilities: ['ANONYMOUS-RELAY'],
                desiredCapabilities: ['DELAYED_DELIVERY'],
            ),
            $codec->decode(
                "\x00\x53\x10\xc0\x3e\x09"
                . "\xa1\x06server"
                . "\x40\x40\x40\x40"
                . "\xa3\x05en-US"
                . "\xa3\x05nl-NL"
                . "\xa3\x0fANONYMOUS-RELAY"
                . "\xa3\x10DELAYED_DELIVERY",
            ),
        );
    }

    public function testDecodesList32OpenPerformative(): void
    {
        $codec = new OpenCodec();

        self::assertEquals(
            new Open(containerId: 'server'),
            $codec->decode("\x00\x53\x10\xd0\x00\x00\x00\x0c\x00\x00\x00\x01\xa1\x06server"),
        );
    }

    public function testRejectsWrongDescriptor(): void
    {
        $codec = new OpenCodec();

        $this->expectException(PerformativeException::class);
        $this->expectExceptionMessage('Expected AMQP open performative descriptor.');

        $codec->decode("\x00\x53\x11\xc0\x09\x01\xa1\x06client");
    }

    public function testRejectsOpenWithoutContainerId(): void
    {
        $codec = new OpenCodec();

        $this->expectException(PerformativeException::class);
        $this->expectExceptionMessage('AMQP open performative requires container-id.');

        $codec->decode("\x00\x53\x10\x45");
    }

    public function testRejectsTruncatedOpenContainerId(): void
    {
        $codec = new OpenCodec();

        $this->expectException(PerformativeException::class);
        $this->expectExceptionMessage('Truncated AMQP open performative.');

        $codec->decode("\x00\x53\x10\xc0\x09\x01\xa1\x06cli");
    }
}
