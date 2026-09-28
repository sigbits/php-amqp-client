<?php

declare(strict_types=1);

namespace Sigbits\Amqp\Protocol\Header;

use InvalidArgumentException;

final readonly class ProtocolHeader
{
    public const int PROTOCOL_ID_AMQP = 0;
    public const int PROTOCOL_ID_SASL = 3;

    public function __construct(
        public int $protocolId,
        public int $major,
        public int $minor,
        public int $revision,
    ) {
        foreach ([$protocolId, $major, $minor, $revision] as $byte) {
            if ($byte < 0 || $byte > 255) {
                throw new InvalidArgumentException('AMQP protocol header bytes must be between 0 and 255.');
            }
        }
    }

    public static function amqp(): self
    {
        return new self(self::PROTOCOL_ID_AMQP, 1, 0, 0);
    }

    public static function sasl(): self
    {
        return new self(self::PROTOCOL_ID_SASL, 1, 0, 0);
    }

    public function isAmqp10(): bool
    {
        return $this->protocolId === self::PROTOCOL_ID_AMQP
            && $this->major === 1
            && $this->minor === 0
            && $this->revision === 0;
    }
}
