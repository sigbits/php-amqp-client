<?php

declare(strict_types=1);

namespace Sigbits\Amqp\Protocol\Performative;

use InvalidArgumentException;
use Sigbits\Amqp\Protocol\Type\AmqpMap;
use Sigbits\Amqp\Protocol\Type\UInt;
use Sigbits\Amqp\Protocol\Type\ULong;

final readonly class Attach
{
    public string $name;
    public int $handle;
    public LinkRole $role;
    public ?string $sourceAddress;
    public ?string $targetAddress;
    public ?int $initialDeliveryCount;
    public ?int $senderSettleMode;
    public ?int $receiverSettleMode;
    public ?ULong $maxMessageSize;
    /** @var list<string> */
    public array $offeredCapabilities;
    /** @var list<string> */
    public array $desiredCapabilities;
    public ?AmqpMap $properties;
    public ?Source $source;
    public ?Target $target;
    public ?AmqpMap $unsettled;
    public ?bool $incompleteUnsettled;

    /**
     * @param list<string> $offeredCapabilities
     * @param list<string> $desiredCapabilities
     */
    public function __construct(
        string $name,
        int $handle,
        LinkRole $role,
        ?string $sourceAddress = null,
        ?string $targetAddress = null,
        ?int $initialDeliveryCount = null,
        ?Source $source = null,
        ?Target $target = null,
        ?int $senderSettleMode = null,
        ?int $receiverSettleMode = null,
        ?ULong $maxMessageSize = null,
        array $offeredCapabilities = [],
        array $desiredCapabilities = [],
        ?AmqpMap $properties = null,
        ?AmqpMap $unsettled = null,
        ?bool $incompleteUnsettled = null,
    ) {
        if ($name === '') {
            throw new InvalidArgumentException('AMQP attach name must not be empty.');
        }

        if (strlen($name) > 255) {
            throw new InvalidArgumentException('AMQP attach name must fit in str8 encoding.');
        }

        new UInt($handle);

        if ($source !== null && $sourceAddress !== null && $source->address !== $sourceAddress) {
            throw new InvalidArgumentException('AMQP attach source and sourceAddress must match.');
        }

        if ($target !== null && $targetAddress !== null && $target->address !== $targetAddress) {
            throw new InvalidArgumentException('AMQP attach target and targetAddress must match.');
        }

        $source ??= $sourceAddress === null ? null : new Source($sourceAddress);
        $target ??= $targetAddress === null ? null : new Target($targetAddress);

        if ($initialDeliveryCount !== null) {
            new UInt($initialDeliveryCount);
        }

        if ($role === LinkRole::Sender && $initialDeliveryCount === null) {
            throw new InvalidArgumentException('AMQP sender attach requires initial-delivery-count.');
        }

        if ($role === LinkRole::Receiver && $initialDeliveryCount !== null) {
            throw new InvalidArgumentException('AMQP receiver attach must not include initial-delivery-count.');
        }

        if ($senderSettleMode !== null && ($senderSettleMode < 0 || $senderSettleMode > 2)) {
            throw new InvalidArgumentException('AMQP sender settle mode must be 0, 1, or 2.');
        }

        if ($receiverSettleMode !== null && ($receiverSettleMode < 0 || $receiverSettleMode > 1)) {
            throw new InvalidArgumentException('AMQP receiver settle mode must be 0 or 1.');
        }

        $this->assertSymbolList($offeredCapabilities, 'offered-capabilities');
        $this->assertSymbolList($desiredCapabilities, 'desired-capabilities');

        $this->name = $name;
        $this->handle = $handle;
        $this->role = $role;
        $this->source = $source;
        $this->target = $target;
        $this->unsettled = $unsettled;
        $this->incompleteUnsettled = $incompleteUnsettled;
        $this->sourceAddress = $source?->address;
        $this->targetAddress = $target?->address;
        $this->initialDeliveryCount = $initialDeliveryCount;
        $this->senderSettleMode = $senderSettleMode;
        $this->receiverSettleMode = $receiverSettleMode;
        $this->maxMessageSize = $maxMessageSize;
        $this->offeredCapabilities = $offeredCapabilities;
        $this->desiredCapabilities = $desiredCapabilities;
        $this->properties = $properties;
    }

    /**
     * @param array<array-key, mixed> $values
     */
    private function assertSymbolList(array $values, string $field): void
    {
        if (!array_is_list($values)) {
            throw new InvalidArgumentException(sprintf('AMQP attach %s must be a list array.', $field));
        }

        foreach ($values as $value) {
            if (!is_string($value) || $value === '') {
                throw new InvalidArgumentException(sprintf('AMQP attach %s entries must be non-empty strings.', $field));
            }
        }
    }
}
