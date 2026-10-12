<?php

declare(strict_types=1);

namespace Sigbits\Amqp\Protocol\Performative;

use InvalidArgumentException;
use Sigbits\Amqp\Protocol\Type\UInt;

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
    public ?Source $source;
    public ?Target $target;

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

        if ($senderSettleMode !== null && ($senderSettleMode < 0 || $senderSettleMode > 2)) {
            throw new InvalidArgumentException('AMQP sender settle mode must be 0, 1, or 2.');
        }

        if ($receiverSettleMode !== null && ($receiverSettleMode < 0 || $receiverSettleMode > 1)) {
            throw new InvalidArgumentException('AMQP receiver settle mode must be 0 or 1.');
        }

        $this->name = $name;
        $this->handle = $handle;
        $this->role = $role;
        $this->source = $source;
        $this->target = $target;
        $this->sourceAddress = $source?->address;
        $this->targetAddress = $target?->address;
        $this->initialDeliveryCount = $initialDeliveryCount;
        $this->senderSettleMode = $senderSettleMode;
        $this->receiverSettleMode = $receiverSettleMode;
    }
}
