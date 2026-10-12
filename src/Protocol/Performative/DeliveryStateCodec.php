<?php

declare(strict_types=1);

namespace Sigbits\Amqp\Protocol\Performative;

use InvalidArgumentException;
use Sigbits\Amqp\Protocol\Codec\DecodeException;
use Sigbits\Amqp\Protocol\Codec\Decoder;
use Sigbits\Amqp\Protocol\Codec\Encoder;
use Sigbits\Amqp\Protocol\Type\AmqpDescribedValue;
use Sigbits\Amqp\Protocol\Type\AmqpList;
use Sigbits\Amqp\Protocol\Type\ULong;

final class DeliveryStateCodec
{
    private const string ACCEPTED_DESCRIPTOR = '36';
    private const string REJECTED_DESCRIPTOR = '37';
    private const string RELEASED_DESCRIPTOR = '38';

    private readonly Encoder $encoder;
    private readonly Decoder $decoder;

    public function __construct(?Encoder $encoder = null, ?Decoder $decoder = null)
    {
        $this->encoder = $encoder ?? new Encoder();
        $this->decoder = $decoder ?? new Decoder();
    }

    public function encode(DeliveryState $state): string
    {
        return $this->encoder->encode(new AmqpDescribedValue(
            new ULong($this->descriptorForState($state)),
            new AmqpList([]),
        ));
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

        if (!$decoded instanceof AmqpDescribedValue
            || !$decoded->descriptor instanceof ULong
            || !$decoded->value instanceof AmqpList
            || $decoded->value->items !== []) {
            throw DecodeException::malformedDescribedValue();
        }

        return [
            match ($decoded->descriptor->value) {
                self::ACCEPTED_DESCRIPTOR => new Accepted(),
                self::REJECTED_DESCRIPTOR => new Rejected(),
                self::RELEASED_DESCRIPTOR => new Released(),
                default => throw DecodeException::malformedDescribedValue(),
            },
            $consumed,
        ];
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

        throw new InvalidArgumentException('Unsupported AMQP delivery state.');
    }
}
