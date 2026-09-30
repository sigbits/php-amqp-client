<?php

declare(strict_types=1);

namespace Sigbits\Amqp\Tests\Protocol\Message;

use PHPUnit\Framework\TestCase;
use Sigbits\Amqp\Protocol\Message\Header;
use Sigbits\Amqp\Protocol\Message\Message;
use Sigbits\Amqp\Protocol\Message\MessageBodySection;
use Sigbits\Amqp\Protocol\Message\MessageCodec;
use Sigbits\Amqp\Protocol\Message\MessageException;
use Sigbits\Amqp\Protocol\Message\Properties;
use Sigbits\Amqp\Protocol\Type\Byte;
use Sigbits\Amqp\Protocol\Type\Int_;
use Sigbits\Amqp\Protocol\Type\Long_;
use Sigbits\Amqp\Protocol\Type\Short;

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

    public function testEncodesLargeDataBodyMessageWithVbin32(): void
    {
        $codec = new MessageCodec();
        $body = str_repeat('x', 300);

        self::assertSame(
            "\x00\x53\x75\xb0\x00\x00\x01\x2c" . $body,
            $codec->encode(new Message(body: $body)),
        );
    }

    public function testDecodesLargeDataBodyMessageWithVbin32(): void
    {
        $codec = new MessageCodec();
        $body = str_repeat('x', 300);

        self::assertEquals(
            new Message(body: $body),
            $codec->decode("\x00\x53\x75\xb0\x00\x00\x01\x2c" . $body),
        );
    }

    public function testEncodesAmqpValueBodyMessage(): void
    {
        $codec = new MessageCodec();

        self::assertSame(
            "\x00\x53\x77\xa1\x05hello",
            $codec->encode(new Message(
                body: 'hello',
                bodySection: MessageBodySection::AmqpValue,
            )),
        );
    }

    public function testDecodesAmqpValueBodyMessage(): void
    {
        $codec = new MessageCodec();

        self::assertEquals(
            new Message(
                body: 'hello',
                bodySection: MessageBodySection::AmqpValue,
            ),
            $codec->decode("\x00\x53\x77\xa1\x05hello"),
        );
    }

    public function testEncodesAmqpSequenceBodyMessage(): void
    {
        $codec = new MessageCodec();

        self::assertSame(
            "\x00\x53\x76\xc0\x0b\x02\xa1\x03one\xa1\x03two",
            $codec->encode(new Message(
                body: '',
                bodySection: MessageBodySection::AmqpSequence,
                bodySequence: ['one', 'two'],
            )),
        );
    }

    public function testDecodesAmqpSequenceBodyMessage(): void
    {
        $codec = new MessageCodec();

        self::assertEquals(
            new Message(
                body: '',
                bodySection: MessageBodySection::AmqpSequence,
                bodySequence: ['one', 'two'],
            ),
            $codec->decode("\x00\x53\x76\xc0\x0b\x02\xa1\x03one\xa1\x03two"),
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

    public function testEncodesDeliveryAnnotationsBeforeDataBody(): void
    {
        $codec = new MessageCodec();

        self::assertSame(
            "\x00\x53\x71\xc1\x16\x02\xa3\x0cdelivery-tag\xa1\x05tag-1"
                . "\x00\x53\x75\xa0\x07payload",
            $codec->encode(new Message(
                body: 'payload',
                deliveryAnnotations: [
                    'delivery-tag' => 'tag-1',
                ],
            )),
        );
    }

    public function testDecodesDeliveryAnnotationsBeforeDataBody(): void
    {
        $codec = new MessageCodec();

        self::assertEquals(
            new Message(
                body: 'payload',
                deliveryAnnotations: [
                    'delivery-tag' => 'tag-1',
                ],
            ),
            $codec->decode(
                "\x00\x53\x71\xc1\x16\x02\xa3\x0cdelivery-tag\xa1\x05tag-1"
                    . "\x00\x53\x75\xa0\x07payload",
            ),
        );
    }

    public function testEncodesDeliveryAnnotationsWithSignedScalarValue(): void
    {
        $codec = new MessageCodec();

        self::assertSame(
            "\x00\x53\x71\xc1\x14\x02\xa3\x0fpriority-offset\x51\x80"
                . "\x00\x53\x75\xa0\x07payload",
            $codec->encode(new Message(
                body: 'payload',
                deliveryAnnotations: [
                    'priority-offset' => new Byte(-128),
                ],
            )),
        );
    }

    public function testDecodesDeliveryAnnotationsWithSignedScalarValue(): void
    {
        $codec = new MessageCodec();

        self::assertEquals(
            new Message(
                body: 'payload',
                deliveryAnnotations: [
                    'priority-offset' => new Byte(-128),
                ],
            ),
            $codec->decode(
                "\x00\x53\x71\xc1\x14\x02\xa3\x0fpriority-offset\x51\x80"
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

    public function testEncodesMessageAnnotationsWithSignedScalarValue(): void
    {
        $codec = new MessageCodec();

        self::assertSame(
            "\x00\x53\x72\xc1\x11\x02\xa3\x0bshard-index\x61\xff\xfe"
                . "\x00\x53\x75\xa0\x07payload",
            $codec->encode(new Message(
                body: 'payload',
                messageAnnotations: [
                    'shard-index' => new Short(-2),
                ],
            )),
        );
    }

    public function testDecodesMessageAnnotationsWithSignedScalarValue(): void
    {
        $codec = new MessageCodec();

        self::assertEquals(
            new Message(
                body: 'payload',
                messageAnnotations: [
                    'shard-index' => new Short(-2),
                ],
            ),
            $codec->decode(
                "\x00\x53\x72\xc1\x11\x02\xa3\x0bshard-index\x61\xff\xfe"
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

    public function testEncodesApplicationPropertiesWithSignedScalarValues(): void
    {
        $codec = new MessageCodec();

        self::assertSame(
            "\x00\x53\x74\xc1\x2c\x08"
                . "\xa3\x04byte\x51\x7f"
                . "\xa3\x05short\x61\x80\x00"
                . "\xa3\x03int\x71\xff\xff\xff\xfd"
                . "\xa3\x04long\x81\xff\xff\xff\xff\xff\xff\xff\xfc"
                . "\x00\x53\x75\xa0\x07payload",
            $codec->encode(new Message(
                body: 'payload',
                applicationProperties: [
                    'byte' => new Byte(127),
                    'short' => new Short(-32768),
                    'int' => new Int_(-3),
                    'long' => new Long_(-4),
                ],
            )),
        );
    }

    public function testDecodesApplicationPropertiesWithSignedScalarValues(): void
    {
        $codec = new MessageCodec();

        self::assertEquals(
            new Message(
                body: 'payload',
                applicationProperties: [
                    'byte' => new Byte(127),
                    'short' => new Short(-32768),
                    'int' => new Int_(-3),
                    'long' => new Long_(-4),
                ],
            ),
            $codec->decode(
                "\x00\x53\x74\xc1\x2c\x08"
                    . "\xa3\x04byte\x51\x7f"
                    . "\xa3\x05short\x61\x80\x00"
                    . "\xa3\x03int\x71\xff\xff\xff\xfd"
                    . "\xa3\x04long\x81\xff\xff\xff\xff\xff\xff\xff\xfc"
                    . "\x00\x53\x75\xa0\x07payload",
            ),
        );
    }

    public function testEncodesFooterAfterDataBody(): void
    {
        $codec = new MessageCodec();

        self::assertSame(
            "\x00\x53\x75\xa0\x07payload"
                . "\x00\x53\x78\xc1\x13\x02\xa3\x08checksum\xa1\x06abc123",
            $codec->encode(new Message(
                body: 'payload',
                footer: [
                    'checksum' => 'abc123',
                ],
            )),
        );
    }

    public function testDecodesFooterAfterDataBody(): void
    {
        $codec = new MessageCodec();

        self::assertEquals(
            new Message(
                body: 'payload',
                footer: [
                    'checksum' => 'abc123',
                ],
            ),
            $codec->decode(
                "\x00\x53\x75\xa0\x07payload"
                    . "\x00\x53\x78\xc1\x13\x02\xa3\x08checksum\xa1\x06abc123",
            ),
        );
    }

    public function testEncodesFooterWithSignedScalarValue(): void
    {
        $codec = new MessageCodec();

        self::assertSame(
            "\x00\x53\x75\xa0\x07payload"
                . "\x00\x53\x78\xc1\x0e\x02\xa3\x06offset\x71\x80\x00\x00\x00",
            $codec->encode(new Message(
                body: 'payload',
                footer: [
                    'offset' => new Int_(-2147483648),
                ],
            )),
        );
    }

    public function testDecodesFooterWithSignedScalarValue(): void
    {
        $codec = new MessageCodec();

        self::assertEquals(
            new Message(
                body: 'payload',
                footer: [
                    'offset' => new Int_(-2147483648),
                ],
            ),
            $codec->decode(
                "\x00\x53\x75\xa0\x07payload"
                    . "\x00\x53\x78\xc1\x0e\x02\xa3\x06offset\x71\x80\x00\x00\x00",
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
        $this->expectExceptionMessage('AMQP message body must use vbin8 or vbin32 data encoding.');

        $codec->decode("\x00\x53\x75\xa1\x05hello");
    }

    public function testRejectsTruncatedDataBody(): void
    {
        $codec = new MessageCodec();

        $this->expectException(MessageException::class);
        $this->expectExceptionMessage('Truncated AMQP data body section.');

        $codec->decode("\x00\x53\x75\xa0\x05hel");
    }

    public function testRejectsApplicationPropertiesWithUnconsumedMapPayload(): void
    {
        $codec = new MessageCodec();

        $this->expectException(MessageException::class);
        $this->expectExceptionMessage('Malformed AMQP application properties section.');

        $codec->decode(
            "\x00\x53\x74\xc1\x08\x02\xa3\x01k\xa1\x01v\x40"
                . "\x00\x53\x75\xa0\x07payload",
        );
    }

    public function testRejectsFooterWithUnconsumedMapPayload(): void
    {
        $codec = new MessageCodec();

        $this->expectException(MessageException::class);
        $this->expectExceptionMessage('Malformed AMQP footer section.');

        $codec->decode(
            "\x00\x53\x75\xa0\x07payload"
                . "\x00\x53\x78\xc1\x08\x02\xa3\x01k\xa1\x01v\x40",
        );
    }
}
