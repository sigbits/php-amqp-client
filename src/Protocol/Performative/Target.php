<?php

declare(strict_types=1);

namespace Sigbits\Amqp\Protocol\Performative;

use InvalidArgumentException;
use Sigbits\Amqp\Protocol\Type\AmqpMap;
use Sigbits\Amqp\Protocol\Type\UInt;

final readonly class Target
{
    /**
     * @param list<string> $capabilities
     */
    public function __construct(
        public ?string $address = null,
        public ?int $durable = null,
        public ?string $expiryPolicy = null,
        public ?int $timeout = null,
        public ?bool $dynamic = null,
        public ?AmqpMap $dynamicNodeProperties = null,
        public array $capabilities = [],
    ) {
        if ($address === '') {
            throw new InvalidArgumentException('AMQP target address must not be empty.');
        }

        if ($address !== null && strlen($address) > 255) {
            throw new InvalidArgumentException('AMQP target address must fit in str8 encoding.');
        }

        if ($durable !== null) {
            new UInt($durable);
        }

        if ($expiryPolicy === '') {
            throw new InvalidArgumentException('AMQP target expiry-policy must not be empty.');
        }

        if ($timeout !== null) {
            new UInt($timeout);
        }

        $this->assertSymbolList($capabilities, 'capabilities');
    }

    /**
     * @param array<array-key, mixed> $values
     */
    private function assertSymbolList(array $values, string $field): void
    {
        if (!array_is_list($values)) {
            throw new InvalidArgumentException(sprintf('AMQP target %s must be a list array.', $field));
        }

        foreach ($values as $value) {
            if (!is_string($value) || $value === '') {
                throw new InvalidArgumentException(sprintf('AMQP target %s entries must be non-empty strings.', $field));
            }
        }
    }
}
