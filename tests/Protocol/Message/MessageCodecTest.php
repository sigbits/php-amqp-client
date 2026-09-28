<?php

declare(strict_types=1);

namespace Sigbits\Amqp\Tests\Protocol\Message;

use PHPUnit\Framework\TestCase;
use Sigbits\Amqp\Protocol\Message\Message;
use Sigbits\Amqp\Protocol\Message\MessageCodec;
use Sigbits\Amqp\Protocol\Message\MessageException;

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

    public function testRejectsWrongSectionDescriptor(): void
    {
        $codec = new MessageCodec();

        $this->expectException(MessageException::class);
        $this->expectExceptionMessage('Expected AMQP data body section descriptor.');

        $codec->decode("\x00\x53\x74\xa0\x05hello");
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
