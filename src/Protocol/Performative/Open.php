<?php

declare(strict_types=1);

namespace Sigbits\Amqp\Protocol\Performative;

use InvalidArgumentException;
use Sigbits\Amqp\Protocol\Type\AmqpMap;
use Sigbits\Amqp\Protocol\Type\UInt;
use Sigbits\Amqp\Protocol\Type\UShort;

final readonly class Open
{
    /**
     * @param list<string> $outgoingLocales
     * @param list<string> $incomingLocales
     * @param list<string> $offeredCapabilities
     * @param list<string> $desiredCapabilities
     */
    public function __construct(
        public string $containerId,
        public ?string $hostname = null,
        public ?int $maxFrameSize = null,
        public ?int $channelMax = null,
        public ?int $idleTimeOut = null,
        public array $outgoingLocales = [],
        public array $incomingLocales = [],
        public array $offeredCapabilities = [],
        public array $desiredCapabilities = [],
        public ?AmqpMap $properties = null,
    ) {
        if ($containerId === '') {
            throw new InvalidArgumentException('AMQP open container-id must not be empty.');
        }

        if ($hostname === '') {
            throw new InvalidArgumentException('AMQP open hostname must not be empty.');
        }

        if ($maxFrameSize !== null) {
            new UInt($maxFrameSize);
        }

        if ($channelMax !== null) {
            new UShort($channelMax);
        }

        if ($idleTimeOut !== null) {
            new UInt($idleTimeOut);
        }

        $this->assertSymbolList($outgoingLocales, 'outgoing-locales');
        $this->assertSymbolList($incomingLocales, 'incoming-locales');
        $this->assertSymbolList($offeredCapabilities, 'offered-capabilities');
        $this->assertSymbolList($desiredCapabilities, 'desired-capabilities');
    }

    /**
     * @param array<array-key, mixed> $values
     */
    private function assertSymbolList(array $values, string $field): void
    {
        if (!array_is_list($values)) {
            throw new InvalidArgumentException(sprintf('AMQP open %s must be a list array.', $field));
        }

        foreach ($values as $value) {
            if (!is_string($value) || $value === '') {
                throw new InvalidArgumentException(sprintf('AMQP open %s entries must be non-empty strings.', $field));
            }
        }
    }
}
