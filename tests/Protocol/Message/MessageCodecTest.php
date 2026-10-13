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
use Sigbits\Amqp\Protocol\Type\AmqpDescribedValue;
use Sigbits\Amqp\Protocol\Type\AmqpList;
use Sigbits\Amqp\Protocol\Type\Binary;
use Sigbits\Amqp\Protocol\Type\Byte;
use Sigbits\Amqp\Protocol\Type\Int_;
use Sigbits\Amqp\Protocol\Type\Long_;
use Sigbits\Amqp\Protocol\Type\Short;
use Sigbits\Amqp\Protocol\Type\Symbol;
use Sigbits\Amqp\Protocol\Type\Timestamp;
use Sigbits\Amqp\Protocol\Type\UInt;
use Sigbits\Amqp\Protocol\Type\ULong;
use Sigbits\Amqp\Protocol\Type\Uuid;

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

    public function testEncodesMultipleDataBodySections(): void
    {
        $codec = new MessageCodec();

        self::assertSame(
            "\x00\x53\x75\xa0\x03one"
                . "\x00\x53\x75\xa0\x03two",
            $codec->encode(new Message(
                body: '',
                dataSections: ['one', 'two'],
            )),
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

    public function testDecodesMultipleDataBodySections(): void
    {
        $codec = new MessageCodec();

        self::assertEquals(
            new Message(
                body: 'one',
                dataSections: ['one', 'two'],
            ),
            $codec->decode(
                "\x00\x53\x75\xa0\x03one"
                    . "\x00\x53\x75\xa0\x03two",
            ),
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
        $message = $codec->decode("\x00\x53\x77\xa1\x05hello");

        self::assertEquals(
            new Message(
                body: 'hello',
                bodySection: MessageBodySection::AmqpValue,
            ),
            $message,
        );
        self::assertFalse($message->hasBodyValue());
    }

    public function testEncodesAmqpValueBodyWithGenericList(): void
    {
        $codec = new MessageCodec();

        self::assertSame(
            "\x00\x53\x77\xc0\x0f\x03\xa1\x06orders\x70\x00\x00\x00\x2a\x41",
            $codec->encode(new Message(
                body: '',
                bodySection: MessageBodySection::AmqpValue,
                bodyValue: new AmqpList(['orders', new UInt(42), true]),
            )),
        );
    }

    public function testDecodesAmqpValueBodyWithGenericList(): void
    {
        $codec = new MessageCodec();

        self::assertEquals(
            new Message(
                body: '',
                bodySection: MessageBodySection::AmqpValue,
                bodyValue: new AmqpList(['orders', new UInt(42), true]),
            ),
            $codec->decode("\x00\x53\x77\xc0\x0f\x03\xa1\x06orders\x70\x00\x00\x00\x2a\x41"),
        );
    }

    public function testEncodesAmqpValueBodyWithBinaryValue(): void
    {
        $codec = new MessageCodec();

        self::assertSame(
            "\x00\x53\x77\xa0\x03abc",
            $codec->encode(new Message(
                body: '',
                bodySection: MessageBodySection::AmqpValue,
                bodyValue: new Binary('abc'),
            )),
        );
    }

    public function testDecodesAmqpValueBodyWithBinaryValue(): void
    {
        $codec = new MessageCodec();
        $message = $codec->decode("\x00\x53\x77\xa0\x03abc");

        self::assertEquals(
            new Message(
                body: '',
                bodySection: MessageBodySection::AmqpValue,
                bodyValue: new Binary('abc'),
            ),
            $message,
        );
        self::assertTrue($message->hasBodyValue());
    }

    public function testEncodesAmqpValueBodyWithDescribedValue(): void
    {
        $codec = new MessageCodec();

        self::assertSame(
            "\x00\x53\x77\x00\x53\x75\xc0\x04\x01\xa1\x01x",
            $codec->encode(new Message(
                body: '',
                bodySection: MessageBodySection::AmqpValue,
                bodyValue: new AmqpDescribedValue(new ULong(117), new AmqpList(['x'])),
            )),
        );
    }

    public function testDecodesAmqpValueBodyWithDescribedValue(): void
    {
        $codec = new MessageCodec();

        self::assertEquals(
            new Message(
                body: '',
                bodySection: MessageBodySection::AmqpValue,
                bodyValue: new AmqpDescribedValue(new ULong(117), new AmqpList(['x'])),
            ),
            $codec->decode("\x00\x53\x77\x00\x53\x75\xc0\x04\x01\xa1\x01x"),
        );
    }

    public function testEncodesAmqpValueBodyWithNullValue(): void
    {
        $codec = new MessageCodec();

        self::assertSame(
            "\x00\x53\x77\x40",
            $codec->encode(new Message(
                body: '',
                bodySection: MessageBodySection::AmqpValue,
                bodyValue: null,
            )),
        );
    }

    public function testDecodesAmqpValueBodyWithNullValue(): void
    {
        $codec = new MessageCodec();
        $message = $codec->decode("\x00\x53\x77\x40");

        self::assertEquals(
            new Message(
                body: '',
                bodySection: MessageBodySection::AmqpValue,
                bodyValue: null,
            ),
            $message,
        );
        self::assertTrue($message->hasBodyValue());
    }

    public function testDecodesFooterAfterAmqpValueBody(): void
    {
        $codec = new MessageCodec();

        self::assertEquals(
            new Message(
                body: '',
                bodySection: MessageBodySection::AmqpValue,
                footer: [
                    'checksum' => 'abc123',
                ],
                bodyValue: new Binary('abc'),
            ),
            $codec->decode(
                "\x00\x53\x77\xa0\x03abc"
                    . "\x00\x53\x78\xc1\x13\x02\xa3\x08checksum\xa1\x06abc123",
            ),
        );
    }

    public function testRejectsTruncatedAmqpValueBody(): void
    {
        $codec = new MessageCodec();

        $this->expectException(MessageException::class);
        $this->expectExceptionMessage('Truncated AMQP value body section.');

        $codec->decode("\x00\x53\x77\xa1\x02x");
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

    public function testEncodesMultipleAmqpSequenceBodySections(): void
    {
        $codec = new MessageCodec();

        self::assertSame(
            "\x00\x53\x76\xc0\x06\x01\xa1\x03one"
                . "\x00\x53\x76\xc0\x0b\x02\xa1\x03two\x70\x00\x00\x00\x02",
            $codec->encode(new Message(
                body: '',
                bodySection: MessageBodySection::AmqpSequence,
                bodySequences: [
                    ['one'],
                    ['two', new UInt(2)],
                ],
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

    public function testDecodesMultipleAmqpSequenceBodySections(): void
    {
        $codec = new MessageCodec();

        self::assertEquals(
            new Message(
                body: '',
                bodySection: MessageBodySection::AmqpSequence,
                bodySequences: [
                    ['one'],
                    ['two', new UInt(2)],
                ],
            ),
            $codec->decode(
                "\x00\x53\x76\xc0\x06\x01\xa1\x03one"
                    . "\x00\x53\x76\xc0\x08\x02\xa1\x03two\x52\x02",
            ),
        );
    }

    public function testEncodesAmqpSequenceBodyWithGenericValues(): void
    {
        $codec = new MessageCodec();

        self::assertSame(
            "\x00\x53\x76\xc0\x23\x07"
                . "\xa1\x06orders"
                . "\x70\x00\x00\x00\x2a"
                . "\x41"
                . "\x40"
                . "\xa0\x03abc"
                . "\xa3\x06status"
                . "\xc0\x04\x01\xa1\x01x",
            $codec->encode(new Message(
                body: '',
                bodySection: MessageBodySection::AmqpSequence,
                bodySequence: [
                    'orders',
                    new UInt(42),
                    true,
                    null,
                    new Binary('abc'),
                    new Symbol('status'),
                    new AmqpList(['x']),
                ],
            )),
        );
    }

    public function testDecodesAmqpSequenceBodyWithGenericValues(): void
    {
        $codec = new MessageCodec();

        self::assertEquals(
            new Message(
                body: '',
                bodySection: MessageBodySection::AmqpSequence,
                bodySequence: [
                    'orders',
                    new UInt(42),
                    true,
                    null,
                    new Binary('abc'),
                    new Symbol('status'),
                    new AmqpList(['x']),
                ],
            ),
            $codec->decode(
                "\x00\x53\x76\xc0\x23\x07"
                    . "\xa1\x06orders"
                    . "\x70\x00\x00\x00\x2a"
                    . "\x41"
                    . "\x40"
                    . "\xa0\x03abc"
                    . "\xa3\x06status"
                    . "\xc0\x04\x01\xa1\x01x",
            ),
        );
    }

    public function testEncodesAmqpSequenceBodyWithList32(): void
    {
        $codec = new MessageCodec();
        $value = str_repeat('x', 256);

        self::assertSame(
            "\x00\x53\x76\xd0\x00\x00\x01\x09\x00\x00\x00\x01"
                . "\xb1\x00\x00\x01\x00" . $value,
            $codec->encode(new Message(
                body: '',
                bodySection: MessageBodySection::AmqpSequence,
                bodySequence: [$value],
            )),
        );
    }

    public function testDecodesAmqpSequenceBodyWithList32(): void
    {
        $codec = new MessageCodec();
        $value = str_repeat('x', 256);

        self::assertEquals(
            new Message(
                body: '',
                bodySection: MessageBodySection::AmqpSequence,
                bodySequence: [$value],
            ),
            $codec->decode(
                "\x00\x53\x76\xd0\x00\x00\x01\x09\x00\x00\x00\x01"
                    . "\xb1\x00\x00\x01\x00" . $value,
            ),
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

    public function testEncodesHeaderWithFirstAcquirerAndDeliveryCountBeforeDataBody(): void
    {
        $codec = new MessageCodec();

        self::assertSame(
            "\x00\x53\x70\xc0\x0b\x05\x41\x50\x04\x40\x42\x70\x00\x00\x00\x05"
                . "\x00\x53\x75\xa0\x07payload",
            $codec->encode(new Message(
                body: 'payload',
                header: new Header(
                    durable: true,
                    priority: 4,
                    firstAcquirer: false,
                    deliveryCount: 5,
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

    public function testDecodesHeaderWithFirstAcquirerAndDeliveryCountBeforeDataBody(): void
    {
        $codec = new MessageCodec();

        $message = $codec->decode(
            "\x00\x53\x70\xc0\x08\x05\x41\x50\x04\x40\x42\x52\x05"
                . "\x00\x53\x75\xa0\x07payload",
        );

        self::assertSame('payload', $message->body);
        self::assertNotNull($message->header);
        self::assertTrue($message->header->durable);
        self::assertSame(4, $message->header->priority);
        self::assertNull($message->header->ttl);
        self::assertFalse($message->header->firstAcquirer);
        self::assertSame(5, $message->header->deliveryCount);
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

    public function testDecodesDeliveryAnnotationsWithUnsignedBrokerScalar(): void
    {
        $codec = new MessageCodec();

        self::assertEquals(
            new Message(
                body: 'payload',
                deliveryAnnotations: [
                    'x-opt-sequence-number' => new ULong(1),
                ],
            ),
            $codec->decode(
                "\x00\x53\x71\xc1\x21\x02"
                    . "\xa3\x15x-opt-sequence-number\x80\x00\x00\x00\x00\x00\x00\x00\x01"
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

    public function testDecodesMessageAnnotationsWithSmallIntBrokerScalar(): void
    {
        $codec = new MessageCodec();

        self::assertEquals(
            new Message(
                body: 'payload',
                messageAnnotations: [
                    'x-opt-message-state' => new Int_(0),
                ],
            ),
            $codec->decode(
                "\x00\x53\x72\xc1\x18\x02"
                    . "\xa3\x13x-opt-message-state\x54\x00"
                    . "\x00\x53\x75\xa0\x07payload",
            ),
        );
    }

    public function testDecodesMessageAnnotationsWithUnsignedBrokerScalar(): void
    {
        $codec = new MessageCodec();

        self::assertEquals(
            new Message(
                body: 'payload',
                messageAnnotations: [
                    'x-opt-sequence-number' => new ULong(1),
                ],
            ),
            $codec->decode(
                "\x00\x53\x72\xc1\x21\x02"
                    . "\xa3\x15x-opt-sequence-number\x80\x00\x00\x00\x00\x00\x00\x00\x01"
                    . "\x00\x53\x75\xa0\x07payload",
            ),
        );
    }

    public function testDecodesMessageAnnotationsWithSmallLongBrokerScalar(): void
    {
        $codec = new MessageCodec();

        self::assertEquals(
            new Message(
                body: 'payload',
                messageAnnotations: [
                    'x-opt-sequence-number' => new Long_(2),
                ],
            ),
            $codec->decode(
                "\x00\x53\x72\xc1\x1a\x02"
                    . "\xa3\x15x-opt-sequence-number\x55\x02"
                    . "\x00\x53\x75\xa0\x07payload",
            ),
        );
    }

    public function testDecodesMessageAnnotationsWithDescribedBrokerValue(): void
    {
        $codec = new MessageCodec();

        self::assertEquals(
            new Message(
                body: 'payload',
                messageAnnotations: [
                    'x-opt-sequence-number' => new AmqpDescribedValue(new ULong(128), new UInt(1)),
                ],
            ),
            $codec->decode(
                "\x00\x53\x72\xc1\x1d\x02"
                    . "\xa3\x15x-opt-sequence-number\x00\x53\x80\x52\x01"
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

    public function testEncodesPropertiesWithChoiceAndRemainingFields(): void
    {
        $codec = new MessageCodec();

        self::assertSame(
            "\x00\x53\x73\xc0\x7b\x0d"
                . "\x80\x00\x00\x00\x00\x00\x00\x00\x2a"
                . "\xa0\x03usr"
                . "\xa1\x06orders"
                . "\xa1\x07created"
                . "\xa1\x07replies"
                . "\x98\x00\x11\x22\x33\x44\x55\x66\x77\x88\x99\xaa\xbb\xcc\xdd\xee\xff"
                . "\xa3\x10application/json"
                . "\xa3\x05utf-8"
                . "\x83\x00\x00\x00\x00\x00\x00\x03\xe8"
                . "\x83\x00\x00\x00\x00\x00\x00\x01\xf4"
                . "\xa1\x06orders"
                . "\x70\x00\x00\x00\x07"
                . "\xa1\x07workers"
                . "\x00\x53\x75\xa0\x07payload",
            $codec->encode(new Message(
                body: 'payload',
                properties: new Properties(
                    messageId: new ULong(42),
                    correlationId: new Uuid('00112233-4455-6677-8899-aabbccddeeff'),
                    contentType: 'application/json',
                    subject: 'created',
                    userId: new Binary('usr'),
                    to: 'orders',
                    replyTo: 'replies',
                    contentEncoding: 'utf-8',
                    absoluteExpiryTime: new Timestamp(1000),
                    creationTime: new Timestamp(500),
                    groupId: 'orders',
                    groupSequence: new UInt(7),
                    replyToGroupId: 'workers',
                ),
            )),
        );
    }

    public function testDecodesPropertiesWithChoiceAndRemainingFields(): void
    {
        $codec = new MessageCodec();

        self::assertEquals(
            new Message(
                body: 'payload',
                properties: new Properties(
                    messageId: new ULong(42),
                    correlationId: new Uuid('00112233-4455-6677-8899-aabbccddeeff'),
                    contentType: 'application/json',
                    subject: 'created',
                    userId: new Binary('usr'),
                    to: 'orders',
                    replyTo: 'replies',
                    contentEncoding: 'utf-8',
                    absoluteExpiryTime: new Timestamp(1000),
                    creationTime: new Timestamp(500),
                    groupId: 'orders',
                    groupSequence: new UInt(7),
                    replyToGroupId: 'workers',
                ),
            ),
            $codec->decode(
                "\x00\x53\x73\xc0\x7b\x0d"
                    . "\x80\x00\x00\x00\x00\x00\x00\x00\x2a"
                    . "\xa0\x03usr"
                    . "\xa1\x06orders"
                    . "\xa1\x07created"
                    . "\xa1\x07replies"
                    . "\x98\x00\x11\x22\x33\x44\x55\x66\x77\x88\x99\xaa\xbb\xcc\xdd\xee\xff"
                    . "\xa3\x10application/json"
                    . "\xa3\x05utf-8"
                    . "\x83\x00\x00\x00\x00\x00\x00\x03\xe8"
                    . "\x83\x00\x00\x00\x00\x00\x00\x01\xf4"
                    . "\xa1\x06orders"
                    . "\x70\x00\x00\x00\x07"
                    . "\xa1\x07workers"
                    . "\x00\x53\x75\xa0\x07payload",
            ),
        );
    }

    public function testEncodesPropertiesWithList32AndString32(): void
    {
        $codec = new MessageCodec();
        $subject = str_repeat('x', 256);

        self::assertSame(
            "\x00\x53\x73\xd0\x00\x00\x01\x0c\x00\x00\x00\x04"
                . "\x40\x40\x40\xb1\x00\x00\x01\x00" . $subject
                . "\x00\x53\x75\xa0\x07payload",
            $codec->encode(new Message(
                body: 'payload',
                properties: new Properties(
                    subject: $subject,
                ),
            )),
        );
    }

    public function testDecodesPropertiesWithList32AndString32(): void
    {
        $codec = new MessageCodec();
        $subject = str_repeat('x', 256);

        self::assertEquals(
            new Message(
                body: 'payload',
                properties: new Properties(
                    subject: $subject,
                ),
            ),
            $codec->decode(
                "\x00\x53\x73\xd0\x00\x00\x01\x0c\x00\x00\x00\x04"
                    . "\x40\x40\x40\xb1\x00\x00\x01\x00" . $subject
                    . "\x00\x53\x75\xa0\x07payload",
            ),
        );
    }

    public function testDecodesDataBodyWhenPropertiesContainUnsupportedFieldType(): void
    {
        $codec = new MessageCodec();

        self::assertEquals(
            new Message(
                body: 'payload',
                properties: new Properties(),
            ),
            $codec->decode(
                "\x00\x53\x73\xc0\x07\x02\x40\xa1\x03abc"
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

    public function testEncodesApplicationPropertiesWithGenericValues(): void
    {
        $codec = new MessageCodec();

        self::assertSame(
            "\x00\x53\x74\xc1\x1f\x04"
                . "\xa3\x04tags\xc0\x0b\x02\xa1\x03new\x70\x00\x00\x00\x2a"
                . "\xa3\x04blob\xa0\x03abc"
                . "\x00\x53\x75\xa0\x07payload",
            $codec->encode(new Message(
                body: 'payload',
                applicationProperties: [
                    'tags' => new AmqpList(['new', new UInt(42)]),
                    'blob' => new Binary('abc'),
                ],
            )),
        );
    }

    public function testDecodesApplicationPropertiesWithGenericValues(): void
    {
        $codec = new MessageCodec();

        self::assertEquals(
            new Message(
                body: 'payload',
                applicationProperties: [
                    'tags' => new AmqpList(['new', new UInt(42)]),
                    'blob' => new Binary('abc'),
                ],
            ),
            $codec->decode(
                "\x00\x53\x74\xc1\x1f\x04"
                    . "\xa3\x04tags\xc0\x0b\x02\xa1\x03new\x70\x00\x00\x00\x2a"
                    . "\xa3\x04blob\xa0\x03abc"
                    . "\x00\x53\x75\xa0\x07payload",
            ),
        );
    }

    public function testEncodesApplicationPropertiesWithMap32(): void
    {
        $codec = new MessageCodec();
        $value = str_repeat('x', 256);

        self::assertSame(
            "\x00\x53\x74\xd1\x00\x00\x01\x12\x00\x00\x00\x02"
                . "\xa3\x07payload\xb1\x00\x00\x01\x00" . $value
                . "\x00\x53\x75\xa0\x07payload",
            $codec->encode(new Message(
                body: 'payload',
                applicationProperties: [
                    'payload' => $value,
                ],
            )),
        );
    }

    public function testDecodesApplicationPropertiesWithMap32(): void
    {
        $codec = new MessageCodec();
        $value = str_repeat('x', 256);

        self::assertEquals(
            new Message(
                body: 'payload',
                applicationProperties: [
                    'payload' => $value,
                ],
            ),
            $codec->decode(
                "\x00\x53\x74\xd1\x00\x00\x01\x12\x00\x00\x00\x02"
                    . "\xa3\x07payload\xb1\x00\x00\x01\x00" . $value
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

    public function testEncodesFooterAfterMultipleDataBodySections(): void
    {
        $codec = new MessageCodec();

        self::assertSame(
            "\x00\x53\x75\xa0\x03one"
                . "\x00\x53\x75\xa0\x03two"
                . "\x00\x53\x78\xc1\x13\x02\xa3\x08checksum\xa1\x06abc123",
            $codec->encode(new Message(
                body: '',
                dataSections: ['one', 'two'],
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

    public function testDecodesFooterAfterMultipleDataBodySections(): void
    {
        $codec = new MessageCodec();

        self::assertEquals(
            new Message(
                body: 'one',
                dataSections: ['one', 'two'],
                footer: [
                    'checksum' => 'abc123',
                ],
            ),
            $codec->decode(
                "\x00\x53\x75\xa0\x03one"
                    . "\x00\x53\x75\xa0\x03two"
                    . "\x00\x53\x78\xc1\x13\x02\xa3\x08checksum\xa1\x06abc123",
            ),
        );
    }

    public function testEncodesFooterWithDescribedValue(): void
    {
        $codec = new MessageCodec();

        self::assertSame(
            "\x00\x53\x75\xa0\x07payload"
                . "\x00\x53\x78\xc1\x11\x02\xa3\x05state\x00\x53\x75\xc0\x04\x01\xa1\x01x",
            $codec->encode(new Message(
                body: 'payload',
                footer: [
                    'state' => new AmqpDescribedValue(new ULong(117), new AmqpList(['x'])),
                ],
            )),
        );
    }

    public function testDecodesFooterWithDescribedValue(): void
    {
        $codec = new MessageCodec();

        self::assertEquals(
            new Message(
                body: 'payload',
                footer: [
                    'state' => new AmqpDescribedValue(new ULong(117), new AmqpList(['x'])),
                ],
            ),
            $codec->decode(
                "\x00\x53\x75\xa0\x07payload"
                    . "\x00\x53\x78\xc1\x11\x02\xa3\x05state\x00\x53\x75\xc0\x04\x01\xa1\x01x",
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

    public function testRejectsMixedBodySectionTypes(): void
    {
        $codec = new MessageCodec();

        $this->expectException(MessageException::class);
        $this->expectExceptionMessage('AMQP message body sections must use one body section type.');

        $codec->decode(
            "\x00\x53\x75\xa0\x03one"
                . "\x00\x53\x76\xc0\x06\x01\xa1\x03two",
        );
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
