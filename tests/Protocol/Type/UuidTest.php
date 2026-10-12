<?php

declare(strict_types=1);

namespace Sigbits\Amqp\Tests\Protocol\Type;

use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use Sigbits\Amqp\Protocol\Type\Uuid;

final class UuidTest extends TestCase
{
    public function testAcceptsCanonicalUuid(): void
    {
        $value = new Uuid('00112233-4455-6677-8899-aabbccddeeff');

        self::assertSame('00112233-4455-6677-8899-aabbccddeeff', $value->value);
    }

    public function testNormalizesUppercaseUuid(): void
    {
        $value = new Uuid('00112233-4455-6677-8899-AABBCCDDEEFF');

        self::assertSame('00112233-4455-6677-8899-aabbccddeeff', $value->value);
    }

    public function testRejectsInvalidUuid(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('AMQP uuid value must use canonical RFC 4122 text form.');

        new Uuid('not-a-uuid');
    }
}
