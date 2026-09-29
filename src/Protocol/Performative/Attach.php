<?php

declare(strict_types=1);

namespace Sigbits\Amqp\Protocol\Performative;

use InvalidArgumentException;
use Sigbits\Amqp\Protocol\Type\UInt;

final readonly class Attach
{
    public function __construct(
        public string $name,
        public int $handle,
        public LinkRole $role,
        public ?string $sourceAddress = null,
        public ?string $targetAddress = null,
        public ?int $initialDeliveryCount = null,
    ) {
        if ($name === '') {
            throw new InvalidArgumentException('AMQP attach name must not be empty.');
        }

        if (strlen($name) > 255) {
            throw new InvalidArgumentException('AMQP attach name must fit in str8 encoding.');
        }

        new UInt($handle);

        foreach ([$sourceAddress, $targetAddress] as $address) {
            if ($address !== null && $address === '') {
                throw new InvalidArgumentException('AMQP attach source and target addresses must not be empty.');
            }

            if ($address !== null && strlen($address) > 255) {
                throw new InvalidArgumentException('AMQP attach source and target addresses must fit in str8 encoding.');
            }
        }

        if ($initialDeliveryCount !== null) {
            new UInt($initialDeliveryCount);
        }
    }
}
