<?php

declare(strict_types=1);

namespace Sigbits\Amqp\Protocol\Performative;

use InvalidArgumentException;
use Sigbits\Amqp\Protocol\Type\AmqpMap;
use Sigbits\Amqp\Protocol\Type\UInt;
use Sigbits\Amqp\Protocol\Type\UShort;

final readonly class Begin
{
    /**
     * @param list<string> $offeredCapabilities
     * @param list<string> $desiredCapabilities
     */
    public function __construct(
        public ?int $remoteChannel,
        public int $nextOutgoingId,
        public int $incomingWindow,
        public int $outgoingWindow,
        public ?int $handleMax = null,
        public array $offeredCapabilities = [],
        public array $desiredCapabilities = [],
        public ?AmqpMap $properties = null,
    ) {
        if ($remoteChannel !== null) {
            new UShort($remoteChannel);
        }

        new UInt($nextOutgoingId);
        new UInt($incomingWindow);
        new UInt($outgoingWindow);

        if ($handleMax !== null) {
            new UInt($handleMax);
        }

        $this->assertSymbolList($offeredCapabilities, 'offered-capabilities');
        $this->assertSymbolList($desiredCapabilities, 'desired-capabilities');
    }

    /**
     * @param array<array-key, mixed> $values
     */
    private function assertSymbolList(array $values, string $field): void
    {
        if (!array_is_list($values)) {
            throw new InvalidArgumentException(sprintf('AMQP begin %s must be a list array.', $field));
        }

        foreach ($values as $value) {
            if (!is_string($value) || $value === '') {
                throw new InvalidArgumentException(sprintf('AMQP begin %s entries must be non-empty strings.', $field));
            }
        }
    }
}
