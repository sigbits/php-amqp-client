<?php

declare(strict_types=1);

namespace Sigbits\Amqp\Tests\Protocol\Sasl;

use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use Sigbits\Amqp\Protocol\Sasl\SaslException;
use Sigbits\Amqp\Protocol\Sasl\SaslMechanisms;
use Sigbits\Amqp\Protocol\Sasl\SaslMechanismsCodec;

final class SaslMechanismsCodecTest extends TestCase
{
    public function testEncodesSingleMechanismPerformative(): void
    {
        $codec = new SaslMechanismsCodec();

        self::assertSame(
            "\x00\x53\x40\xc0\x0f\x01\xe0\x0c\x01\xa3\x09ANONYMOUS",
            $codec->encode(new SaslMechanisms(['ANONYMOUS'])),
        );
    }

    public function testDecodesSingleMechanismPerformative(): void
    {
        $codec = new SaslMechanismsCodec();

        self::assertEquals(
            new SaslMechanisms(['ANONYMOUS']),
            $codec->decode("\x00\x53\x40\xc0\x0f\x01\xe0\x0c\x01\xa3\x09ANONYMOUS"),
        );
    }

    public function testEncodesMultipleMechanismsPerformative(): void
    {
        $codec = new SaslMechanismsCodec();

        self::assertSame(
            "\x00\x53\x40\xc0\x15\x01\xe0\x12\x02\xa3\x09ANONYMOUS\x05PLAIN",
            $codec->encode(new SaslMechanisms(['ANONYMOUS', 'PLAIN'])),
        );
    }

    public function testDecodesMultipleMechanismsPerformative(): void
    {
        $codec = new SaslMechanismsCodec();

        self::assertEquals(
            new SaslMechanisms(['ANONYMOUS', 'PLAIN']),
            $codec->decode("\x00\x53\x40\xc0\x15\x01\xe0\x12\x02\xa3\x09ANONYMOUS\x05PLAIN"),
        );
    }

    public function testDecodesSymbol32MechanismArray(): void
    {
        $codec = new SaslMechanismsCodec();

        self::assertEquals(
            new SaslMechanisms(['PLAIN', 'ANONYMOUS']),
            $codec->decode("\x00\x53\x40\xc0\x1b\x01\xe0\x18\x02\xb3\x00\x00\x00\x05PLAIN\x00\x00\x00\x09ANONYMOUS"),
        );
    }

    public function testRejectsWrongDescriptor(): void
    {
        $codec = new SaslMechanismsCodec();

        $this->expectException(SaslException::class);
        $this->expectExceptionMessage('Expected AMQP SASL mechanisms performative descriptor.');

        $codec->decode("\x00\x53\x44\x45");
    }

    public function testRejectsMechanismsWithoutServerMechanisms(): void
    {
        $codec = new SaslMechanismsCodec();

        $this->expectException(SaslException::class);
        $this->expectExceptionMessage('AMQP SASL mechanisms performative requires server mechanisms.');

        $codec->decode("\x00\x53\x40\x45");
    }

    public function testRejectsTruncatedMechanisms(): void
    {
        $codec = new SaslMechanismsCodec();

        $this->expectException(SaslException::class);
        $this->expectExceptionMessage('Truncated AMQP SASL mechanisms performative.');

        $codec->decode("\x00\x53\x40\xc0\x15\x01\xe0\x12\x02\xa3\x09ANONYMOUS\x05PL");
    }

    public function testRejectsEmptyServerMechanismList(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('AMQP SASL server mechanisms must not be empty.');

        new SaslMechanisms([]);
    }
}
