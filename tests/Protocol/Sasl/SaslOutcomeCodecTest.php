<?php

declare(strict_types=1);

namespace Sigbits\Amqp\Tests\Protocol\Sasl;

use PHPUnit\Framework\TestCase;
use Sigbits\Amqp\Protocol\Sasl\SaslException;
use Sigbits\Amqp\Protocol\Sasl\SaslOutcome;
use Sigbits\Amqp\Protocol\Sasl\SaslOutcomeCode;
use Sigbits\Amqp\Protocol\Sasl\SaslOutcomeCodec;

final class SaslOutcomeCodecTest extends TestCase
{
    public function testEncodesOkOutcomePerformative(): void
    {
        $codec = new SaslOutcomeCodec();

        self::assertSame(
            "\x00\x53\x44\xc0\x03\x01\x50\x00",
            $codec->encode(SaslOutcome::ok()),
        );
    }

    public function testDecodesOkOutcomePerformative(): void
    {
        $codec = new SaslOutcomeCodec();

        self::assertEquals(
            SaslOutcome::ok(),
            $codec->decode("\x00\x53\x44\xc0\x03\x01\x50\x00"),
        );
    }

    public function testDecodesOkOutcomePerformativeWithNullAdditionalData(): void
    {
        $codec = new SaslOutcomeCodec();

        self::assertEquals(
            SaslOutcome::ok(),
            $codec->decode("\x00\x53\x44\xc0\x04\x02\x50\x00\x40"),
        );
    }

    public function testEncodesAuthFailureOutcomePerformativeWithAdditionalData(): void
    {
        $codec = new SaslOutcomeCodec();

        self::assertSame(
            "\x00\x53\x44\xc0\x09\x02\x50\x01\xa0\x04nope",
            $codec->encode(new SaslOutcome(SaslOutcomeCode::Auth, 'nope')),
        );
    }

    public function testDecodesAuthFailureOutcomePerformativeWithAdditionalData(): void
    {
        $codec = new SaslOutcomeCodec();

        self::assertEquals(
            new SaslOutcome(SaslOutcomeCode::Auth, 'nope'),
            $codec->decode("\x00\x53\x44\xc0\x09\x02\x50\x01\xa0\x04nope"),
        );
    }

    public function testRejectsWrongDescriptor(): void
    {
        $codec = new SaslOutcomeCodec();

        $this->expectException(SaslException::class);
        $this->expectExceptionMessage('Expected AMQP SASL outcome performative descriptor.');

        $codec->decode("\x00\x53\x41\x45");
    }

    public function testRejectsOutcomeWithoutCode(): void
    {
        $codec = new SaslOutcomeCodec();

        $this->expectException(SaslException::class);
        $this->expectExceptionMessage('AMQP SASL outcome performative requires code.');

        $codec->decode("\x00\x53\x44\x45");
    }

    public function testRejectsTruncatedOutcome(): void
    {
        $codec = new SaslOutcomeCodec();

        $this->expectException(SaslException::class);
        $this->expectExceptionMessage('Truncated AMQP SASL outcome performative.');

        $codec->decode("\x00\x53\x44\xc0\x09\x02\x50\x01\xa0\x04no");
    }
}
