<?php

declare(strict_types=1);

namespace Sigbits\Amqp\Protocol\Performative;

use InvalidArgumentException;
use Sigbits\Amqp\Protocol\Codec\DecodeException;
use Sigbits\Amqp\Protocol\Codec\Decoder;
use Sigbits\Amqp\Protocol\Codec\Encoder;
use Sigbits\Amqp\Protocol\Type\AmqpDescribedValue;
use Sigbits\Amqp\Protocol\Type\AmqpList;
use Sigbits\Amqp\Protocol\Type\AmqpMap;
use Sigbits\Amqp\Protocol\Type\ULong;

final class DeliveryStateCodec
{
    private const string ACCEPTED_DESCRIPTOR = '36';
    private const string REJECTED_DESCRIPTOR = '37';
    private const string RELEASED_DESCRIPTOR = '38';
    private const string MODIFIED_DESCRIPTOR = '39';

    private readonly Encoder $encoder;
    private readonly Decoder $decoder;

    public function __construct(?Encoder $encoder = null, ?Decoder $decoder = null)
    {
        $this->encoder = $encoder ?? new Encoder();
        $this->decoder = $decoder ?? new Decoder();
    }

    public function encode(DeliveryState $state): string
    {
        return $this->encoder->encode($this->toDescribedValue($state));
    }

    public function decode(string $bytes): DeliveryState
    {
        [$state, $consumed] = $this->decodeWithConsumed($bytes);

        if ($consumed !== strlen($bytes)) {
            throw DecodeException::malformedDescribedValue();
        }

        return $state;
    }

    /**
     * @return array{0: DeliveryState, 1: int}
     */
    public function decodeWithConsumed(string $bytes): array
    {
        try {
            [$decoded, $consumed] = $this->decoder->decodeWithConsumed($bytes);
        } catch (DecodeException|InvalidArgumentException $exception) {
            if (str_starts_with($exception->getMessage(), 'Truncated AMQP')) {
                throw DecodeException::truncatedDescribedValue();
            }

            throw DecodeException::malformedDescribedValue();
        }

        if (!$decoded instanceof AmqpDescribedValue) {
            throw DecodeException::malformedDescribedValue();
        }

        return [
            $this->fromDescribedValue($decoded),
            $consumed,
        ];
    }

    public function toDescribedValue(DeliveryState $state): AmqpDescribedValue
    {
        return new AmqpDescribedValue(
            new ULong($this->descriptorForState($state)),
            $this->valueForState($state),
        );
    }

    public function fromDescribedValue(AmqpDescribedValue $value): DeliveryState
    {
        if (!$value->descriptor instanceof ULong || !$value->value instanceof AmqpList) {
            throw DecodeException::malformedDescribedValue();
        }

        return match ($value->descriptor->value) {
            self::ACCEPTED_DESCRIPTOR => $this->emptyState($value, new Accepted()),
            self::REJECTED_DESCRIPTOR => $this->emptyState($value, new Rejected()),
            self::RELEASED_DESCRIPTOR => $this->emptyState($value, new Released()),
            self::MODIFIED_DESCRIPTOR => $this->modifiedState($value->value),
            default => throw DecodeException::malformedDescribedValue(),
        };
    }

    private function descriptorForState(DeliveryState $state): string
    {
        if ($state instanceof Accepted) {
            return self::ACCEPTED_DESCRIPTOR;
        }

        if ($state instanceof Rejected) {
            return self::REJECTED_DESCRIPTOR;
        }

        if ($state instanceof Released) {
            return self::RELEASED_DESCRIPTOR;
        }

        if ($state instanceof Modified) {
            return self::MODIFIED_DESCRIPTOR;
        }

        throw new InvalidArgumentException('Unsupported AMQP delivery state.');
    }

    private function valueForState(DeliveryState $state): AmqpList
    {
        if (!$state instanceof Modified) {
            return new AmqpList([]);
        }

        $fields = [
            $state->deliveryFailed,
            $state->undeliverableHere,
            $state->messageAnnotations,
        ];
        $lastSetField = -1;

        foreach ($fields as $index => $field) {
            if ($field !== null) {
                $lastSetField = $index;
            }
        }

        if ($lastSetField === -1) {
            return new AmqpList([]);
        }

        return new AmqpList(array_slice($fields, 0, $lastSetField + 1));
    }

    private function emptyState(AmqpDescribedValue $value, DeliveryState $state): DeliveryState
    {
        if (!$value->value instanceof AmqpList || $value->value->items !== []) {
            throw DecodeException::malformedDescribedValue();
        }

        return $state;
    }

    private function modifiedState(AmqpList $value): Modified
    {
        if (count($value->items) > 3) {
            throw DecodeException::malformedDescribedValue();
        }

        $deliveryFailed = $value->items[0] ?? null;
        $undeliverableHere = $value->items[1] ?? null;
        $messageAnnotations = $value->items[2] ?? null;

        if (($deliveryFailed !== null && !is_bool($deliveryFailed))
            || ($undeliverableHere !== null && !is_bool($undeliverableHere))
            || ($messageAnnotations !== null && !$messageAnnotations instanceof AmqpMap)) {
            throw DecodeException::malformedDescribedValue();
        }

        return new Modified(
            deliveryFailed: $deliveryFailed,
            undeliverableHere: $undeliverableHere,
            messageAnnotations: $messageAnnotations,
        );
    }
}
