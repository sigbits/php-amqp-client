<?php

declare(strict_types=1);

namespace Sigbits\Amqp\Protocol\Performative;

use InvalidArgumentException;
use Sigbits\Amqp\Protocol\Codec\DecodeException;
use Sigbits\Amqp\Protocol\Codec\Decoder;
use Sigbits\Amqp\Protocol\Codec\Encoder;
use Sigbits\Amqp\Protocol\Codec\ScalarReader;
use Sigbits\Amqp\Protocol\Type\AmqpArray;
use Sigbits\Amqp\Protocol\Type\AmqpDescribedValue;
use Sigbits\Amqp\Protocol\Type\AmqpList;
use Sigbits\Amqp\Protocol\Type\AmqpMap;
use Sigbits\Amqp\Protocol\Type\Symbol;
use Sigbits\Amqp\Protocol\Type\UInt;
use Sigbits\Amqp\Protocol\Type\ULong;
use Sigbits\Amqp\Protocol\Type\UShort;

final class BeginCodec
{
    private const string DESCRIPTOR = "\x00\x53\x11";
    private const int DESCRIPTOR_LENGTH = 3;

    private readonly Encoder $encoder;
    private readonly Decoder $decoder;

    public function __construct(
        ?ScalarReader $scalarReader = null,
        ?Encoder $encoder = null,
        ?Decoder $decoder = null,
    ) {
        unset($scalarReader);

        $this->encoder = $encoder ?? new Encoder();
        $this->decoder = $decoder ?? new Decoder();
    }

    public function encode(Begin $begin): string
    {
        $fields = [
            $begin->remoteChannel === null ? null : new UShort($begin->remoteChannel),
            new UInt($begin->nextOutgoingId),
            new UInt($begin->incomingWindow),
            new UInt($begin->outgoingWindow),
            $begin->handleMax === null ? null : new UInt($begin->handleMax),
            $this->encodeMultipleSymbols($begin->offeredCapabilities),
            $this->encodeMultipleSymbols($begin->desiredCapabilities),
            $begin->properties,
        ];

        while (count($fields) > 4 && end($fields) === null) {
            array_pop($fields);
        }

        return self::DESCRIPTOR . $this->encoder->encode(new AmqpList($fields));
    }

    public function decode(string $bytes): Begin
    {
        if (strlen($bytes) < self::DESCRIPTOR_LENGTH + 1) {
            throw PerformativeException::truncatedBegin();
        }

        if (substr($bytes, 0, self::DESCRIPTOR_LENGTH) !== self::DESCRIPTOR) {
            throw PerformativeException::expectedBeginDescriptor();
        }

        try {
            [$decoded, $consumed] = $this->decoder->decodeWithConsumed($bytes);
        } catch (DecodeException $exception) {
            if (str_starts_with($exception->getMessage(), 'Malformed AMQP list')) {
                throw PerformativeException::malformedBegin();
            }

            throw PerformativeException::truncatedBegin();
        } catch (InvalidArgumentException) {
            throw PerformativeException::truncatedBegin();
        }

        if ($consumed !== strlen($bytes)) {
            throw PerformativeException::malformedBegin();
        }

        if (!$decoded instanceof AmqpDescribedValue
            || !$decoded->descriptor instanceof ULong
            || $decoded->descriptor->value !== '17') {
            throw PerformativeException::expectedBeginDescriptor();
        }

        if (!$decoded->value instanceof AmqpList) {
            throw PerformativeException::truncatedBegin();
        }

        $fields = $decoded->value->items;

        if (count($fields) < 4) {
            throw PerformativeException::missingBeginRequiredFields();
        }

        return new Begin(
            remoteChannel: $this->nullableUShort($fields[0]),
            nextOutgoingId: $this->requiredUInt($fields[1]),
            incomingWindow: $this->requiredUInt($fields[2]),
            outgoingWindow: $this->requiredUInt($fields[3]),
            handleMax: $this->nullableUInt($fields[4] ?? null),
            offeredCapabilities: $this->multipleSymbols($fields[5] ?? null),
            desiredCapabilities: $this->multipleSymbols($fields[6] ?? null),
            properties: $this->nullableMap($fields[7] ?? null),
        );
    }

    /**
     * @param list<string> $values
     */
    private function encodeMultipleSymbols(array $values): ?AmqpArray
    {
        if ($values === []) {
            return null;
        }

        return new AmqpArray(
            "\xa3",
            array_map(static fn (string $value): Symbol => new Symbol($value), $values),
        );
    }

    private function nullableUShort(mixed $value): ?int
    {
        if ($value === null) {
            return null;
        }

        if ($value instanceof UShort) {
            return $value->value;
        }

        throw PerformativeException::missingBeginRequiredFields();
    }

    private function requiredUInt(mixed $value): int
    {
        return $this->nullableUInt($value)
            ?? throw PerformativeException::missingBeginRequiredFields();
    }

    private function nullableUInt(mixed $value): ?int
    {
        if ($value === null) {
            return null;
        }

        if ($value instanceof UInt) {
            return $value->value;
        }

        throw PerformativeException::missingBeginRequiredFields();
    }

    /**
     * @return list<string>
     */
    private function multipleSymbols(mixed $value): array
    {
        if ($value === null) {
            return [];
        }

        if ($value instanceof Symbol) {
            return [$value->value];
        }

        if ($value instanceof AmqpArray && in_array($value->elementConstructor, ["\xa3", "\xb3"], true)) {
            return array_map(
                static fn (Symbol $symbol): string => $symbol->value,
                $value->items,
            );
        }

        throw PerformativeException::truncatedBegin();
    }

    private function nullableMap(mixed $value): ?AmqpMap
    {
        if ($value === null || $value instanceof AmqpMap) {
            return $value;
        }

        throw PerformativeException::truncatedBegin();
    }
}
