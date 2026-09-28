<?php

declare(strict_types=1);

namespace Sigbits\Amqp\Protocol\Sasl;

use InvalidArgumentException;

final readonly class SaslOutcome
{
    public function __construct(
        public SaslOutcomeCode $code,
        public ?string $additionalData = null,
    ) {
        if ($additionalData !== null && strlen($additionalData) > 255) {
            throw new InvalidArgumentException('AMQP SASL additional data must fit in vbin8 encoding.');
        }
    }

    public static function ok(): self
    {
        return new self(SaslOutcomeCode::Ok);
    }
}
