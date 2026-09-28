<?php

declare(strict_types=1);

namespace Sigbits\Amqp\Tests\Protocol\Message;

use PHPUnit\Framework\TestCase;
use Sigbits\Amqp\Protocol\Message\Header;
use Sigbits\Amqp\Protocol\Message\Message;
use Sigbits\Amqp\Protocol\Message\MessageCodec;
use Sigbits\Amqp\Protocol\Message\MessageException;
use Sigbits\Amqp\Protocol\Message\Properties;

final class MessageCodecTest extends TestCase
{
    public function testEncodesDataBodyMessage(): void
    {
        $codec = new MessageCodec();

        self::assertSame(
            "\x00\x53\x75\xa0\x05hello",
            $codec->encode(new Message(body: 'hello')),
        );
    }

    public function testDecodesDataBodyMessage(): void
    {
        $codec = new MessageCodec();

        self::assertEquals(
            new Message(body: 'hello'),
            $codec->decode("\x00\x53\x75\xa0\x05hello"),
        );
    }

    public function testEncodesHeaderBeforeDataBody(): void
    {
        $codec = new MessageCodec();

        self::assertSame(
            "\x00\x53\x70\xc0\x09\x03\x41\x50\x05\x70\x00\x00\xea\x60"
                . "\x00\x53\x75\xa0\x07payload",
            $codec->encode(new Message(
                body: 'payload',
                header: new Header(
                    durable: true,
                    priority: 5,
                    ttl: 60000,
                ),
            )),
        );
    }

    public function testDecodesHeaderBeforeDataBody(): void
    {
        $codec = new MessageCodec();

        self::assertEquals(
            new Message(
                body: 'payload',
                header: new Header(
                    durable: true,
                    priority: 5,
                    ttl: 60000,
                ),
            ),
            $codec->decode(
                "\x00\x53\x70\xc0\x09\x03\x41\x50\x05\x70\x00\x00\xea\x60"
                    . "\x00\x53\x75\xa0\x07payload",
            ),
        );
    }

    public function testEncodesMessageAnnotationsBeforeDataBody(): void
    {
        $codec = new MessageCodec();

        self::assertSame(
            "\x00\x53\x72\xc1\x15\x02\xa3\x08trace-id\xa1\x08trace-42"
                . "\x00\x53\x75\xa0\x07payload",
            $codec->encode(new Message(
                body: 'payload',
                messageAnnotations: [
                    'trace-id' => 'trace-42',
                ],
            )),
        );
    }

    public function testDecodesMessageAnnotationsBeforeDataBody(): void
    {
        $codec = new MessageCodec();

        self::assertEquals(
            new Message(
                body: 'payload',
                messageAnnotations: [
                    'trace-id' => 'trace-42',
                ],
            ),
            $codec->decode(
                "\x00\x53\x72\xc1\x15\x02\xa3\x08trace-id\xa1\x08trace-42"
                    . "\x00\x53\x75\xa0\x07payload",
            ),
        );
    }

    public function testEncodesPropertiesBeforeDataBody(): void
    {
        $codec = new MessageCodec();

        self::assertSame(
            "\x00\x53\x73\xc0\x2f\x07\xa1\x03123\x40\x40\xa1\x0dorder.created\x40\xa1\x03456\xa3\x10application/json"
                . "\x00\x53\x75\xa0\x07payload",
            $codec->encode(new Message(
                body: 'payload',
                properties: new Properties(
                    messageId: '123',
                    correlationId: '456',
                    contentType: 'application/json',
                    subject: 'order.created',
                ),
            )),
        );
    }

    public function testDecodesPropertiesBeforeDataBody(): void
    {
        $codec = new MessageCodec();

        self::assertEquals(
            new Message(
                body: 'payload',
                properties: new Properties(
                    messageId: '123',
                    correlationId: '456',
                    contentType: 'application/json',
                    subject: 'order.created',
                ),
            ),
            $codec->decode(
                "\x00\x53\x73\xc0\x2f\x07\xa1\x03123\x40\x40\xa1\x0dorder.created\x40\xa1\x03456\xa3\x10application/json"
                    . "\x00\x53\x75\xa0\x07payload",
            ),
        );
    }

    public function testEncodesApplicationPropertiesBeforeDataBody(): void
    {
        $codec = new MessageCodec();

        self::assertSame(
            "\x00\x53\x74\xc1\x0f\x02\xa3\x06tenant\xa1\x04acme"
                . "\x00\x53\x75\xa0\x07payload",
            $codec->encode(new Message(
                body: 'payload',
                applicationProperties: [
                    'tenant' => 'acme',
                ],
            )),
        );
    }

    public function testDecodesApplicationPropertiesBeforeDataBody(): void
    {
        $codec = new MessageCodec();

        self::assertEquals(
            new Message(
                body: 'payload',
                applicationProperties: [
                    'tenant' => 'acme',
                ],
            ),
            $codec->decode(
                "\x00\x53\x74\xc1\x0f\x02\xa3\x06tenant\xa1\x04acme"
                    . "\x00\x53\x75\xa0\x07payload",
            ),
        );
    }

    public function testRejectsWrongSectionDescriptor(): void
    {
        $codec = new MessageCodec();

        $this->expectException(MessageException::class);
        $this->expectExceptionMessage('Expected AMQP data body section descriptor.');

        $codec->decode("\x00\x53\x7f\xa0\x05hello");
    }

    public function testRejectsUnsupportedBodyEncoding(): void
    {
        $codec = new MessageCodec();

        $this->expectException(MessageException::class);
        $this->expectExceptionMessage('AMQP message body must use vbin8 data encoding.');

        $codec->decode("\x00\x53\x75\xa1\x05hello");
    }

    public function testRejectsTruncatedDataBody(): void
    {
        $codec = new MessageCodec();

        $this->expectException(MessageException::class);
        $this->expectExceptionMessage('Truncated AMQP data body section.');

        $codec->decode("\x00\x53\x75\xa0\x05hel");
    }
}
