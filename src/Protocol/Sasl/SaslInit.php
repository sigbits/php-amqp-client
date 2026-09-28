<?php

declare(strict_types=1);

namespace Sigbits\Amqp\Protocol\Sasl;

use InvalidArgumentException;

final readonly class SaslInit
{
    public function __construct(
        public string $mechanism,
        public ?string $initialResponse,
    ) {
        if ($mechanism === '') {
            throw new InvalidArgumentException('AMQP SASL mechanism must not be empty.');
        }

        if (strlen($mechanism) > 255) {
            throw new InvalidArgumentException('AMQP SASL mechanism must fit in symbol8 encoding.');
        }

        if ($initialResponse !== null && strlen($initialResponse) > 255) {
            throw new InvalidArgumentException('AMQP SASL initial response must fit in vbin8 encoding.');
        }
    }

    public static function anonymous(): self
    {
        return new self('ANONYMOUS', null);
    }

    public static function plain(string $username, string $password, string $authorizationId = ''): self
    {
        return new self('PLAIN', $authorizationId . "\x00" . $username . "\x00" . $password);
    }
}
