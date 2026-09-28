<?php

declare(strict_types=1);

namespace Sigbits\Amqp\Tests\Protocol\Sasl;

use PHPUnit\Framework\TestCase;
use Sigbits\Amqp\Protocol\Sasl\SaslException;
use Sigbits\Amqp\Protocol\Sasl\SaslInit;
use Sigbits\Amqp\Protocol\Sasl\SaslInitCodec;

final class SaslInitCodecTest extends TestCase
{
    public function testEncodesAnonymousInitPerformative(): void
    {
        $codec = new SaslInitCodec();

        self::assertSame(
            "\x00\x53\x41\xc0\x0c\x01\xa3\x09ANONYMOUS",
            $codec->encode(SaslInit::anonymous()),
        );
    }

    public function testDecodesAnonymousInitPerformative(): void
    {
        $codec = new SaslInitCodec();

        self::assertEquals(
            SaslInit::anonymous(),
            $codec->decode("\x00\x53\x41\xc0\x0c\x01\xa3\x09ANONYMOUS"),
        );
    }

    public function testEncodesPlainInitPerformative(): void
    {
        $codec = new SaslInitCodec();

        self::assertSame(
            "\x00\x53\x41\xc0\x14\x02\xa3\x05PLAIN\xa0\x0a\x00user\x00pass",
            $codec->encode(SaslInit::plain(username: 'user', password: 'pass')),
        );
    }

    public function testDecodesPlainInitPerformative(): void
    {
        $codec = new SaslInitCodec();

        self::assertEquals(
            SaslInit::plain(username: 'user', password: 'pass'),
            $codec->decode("\x00\x53\x41\xc0\x14\x02\xa3\x05PLAIN\xa0\x0a\x00user\x00pass"),
        );
    }

    public function testRejectsWrongDescriptor(): void
    {
        $codec = new SaslInitCodec();

        $this->expectException(SaslException::class);
        $this->expectExceptionMessage('Expected AMQP SASL init performative descriptor.');

        $codec->decode("\x00\x53\x40\x45");
    }

    public function testRejectsInitWithoutMechanism(): void
    {
        $codec = new SaslInitCodec();

        $this->expectException(SaslException::class);
        $this->expectExceptionMessage('AMQP SASL init performative requires mechanism.');

        $codec->decode("\x00\x53\x41\x45");
    }

    public function testRejectsTruncatedInit(): void
    {
        $codec = new SaslInitCodec();

        $this->expectException(SaslException::class);
        $this->expectExceptionMessage('Truncated AMQP SASL init performative.');

        $codec->decode("\x00\x53\x41\xc0\x0c\x01\xa3\x09ANON");
    }
}
