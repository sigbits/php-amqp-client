<?php

declare(strict_types=1);

namespace Sigbits\Amqp\Protocol\Header;

final class ProtocolHeaderCodec
{
    private const string MAGIC = 'AMQP';
    private const int LENGTH = 8;

    public function encode(ProtocolHeader $header): string
    {
        return self::MAGIC
            . chr($header->protocolId)
            . chr($header->major)
            . chr($header->minor)
            . chr($header->revision);
    }

    public function decode(string $bytes): ProtocolHeader
    {
        if (strlen($bytes) < self::LENGTH) {
            throw ProtocolHeaderException::truncated();
        }

        if (substr($bytes, 0, 4) !== self::MAGIC) {
            throw ProtocolHeaderException::invalidMagic();
        }

        return new ProtocolHeader(
            ord($bytes[4]),
            ord($bytes[5]),
            ord($bytes[6]),
            ord($bytes[7]),
        );
    }
}
