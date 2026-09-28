<?php

declare(strict_types=1);

namespace Sigbits\Amqp\Protocol\Sasl;

use InvalidArgumentException;

final readonly class SaslMechanisms
{
    /**
     * @param list<string> $serverMechanisms
     */
    public function __construct(
        public array $serverMechanisms,
    ) {
        if ($serverMechanisms === []) {
            throw new InvalidArgumentException('AMQP SASL server mechanisms must not be empty.');
        }

        foreach ($serverMechanisms as $mechanism) {
            if ($mechanism === '') {
                throw new InvalidArgumentException('AMQP SASL server mechanism must not be empty.');
            }

            if (strlen($mechanism) > 255) {
                throw new InvalidArgumentException('AMQP SASL server mechanism must fit in symbol8 encoding.');
            }
        }
    }
}
