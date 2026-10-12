<?php

declare(strict_types=1);

namespace Sigbits\Amqp\Protocol\Performative;

use InvalidArgumentException;
use Sigbits\Amqp\Protocol\Codec\DecodeException;
use Sigbits\Amqp\Protocol\Codec\Decoder;
use Sigbits\Amqp\Protocol\Codec\Encoder;
use Sigbits\Amqp\Protocol\Type\AmqpArray;
use Sigbits\Amqp\Protocol\Type\AmqpDescribedValue;
use Sigbits\Amqp\Protocol\Type\AmqpList;
use Sigbits\Amqp\Protocol\Type\AmqpMap;
use Sigbits\Amqp\Protocol\Type\Symbol;
use Sigbits\Amqp\Protocol\Type\UInt;
use Sigbits\Amqp\Protocol\Type\ULong;
use Sigbits\Amqp\Protocol\Type\UShort;

final class OpenCodec
{
    private const string DESCRIPTOR = "\x00\x53\x10";
    private const int DESCRIPTOR_LENGTH = 3;

    private readonly Encoder $encoder;
    private readonly Decoder $decoder;

    public function __construct(?Encoder $encoder = null, ?Decoder $decoder = null)
    {
        $this->encoder = $encoder ?? new Encoder();
        $this->decoder = $decoder ?? new Decoder();
    }

    public function encode(Open $open): string
    {
        $fields = [
            $open->containerId,
            $open->hostname,
            $open->maxFrameSize === null ? null : new UInt($open->maxFrameSize),
            $open->channelMax === null ? null : new UShort($open->channelMax),
            $open->idleTimeOut === null ? null : new UInt($open->idleTimeOut),
            $this->encodeMultipleSymbols($open->outgoingLocales),
            $this->encodeMultipleSymbols($open->incomingLocales),
            $this->encodeMultipleSymbols($open->offeredCapabilities),
            $this->encodeMultipleSymbols($open->desiredCapabilities),
            $open->properties,
        ];

        while (count($fields) > 1 && end($fields) === null) {
            array_pop($fields);
        }

        return self::DESCRIPTOR . $this->encoder->encode(new AmqpList($fields));
    }

    public function decode(string $bytes): Open
    {
        if (strlen($bytes) < self::DESCRIPTOR_LENGTH + 1) {
            throw PerformativeException::truncatedOpen();
        }

        if (substr($bytes, 0, self::DESCRIPTOR_LENGTH) !== self::DESCRIPTOR) {
            throw PerformativeException::expectedOpenDescriptor();
        }

        try {
            [$decoded] = $this->decoder->decodeWithConsumed($bytes);
        } catch (DecodeException|InvalidArgumentException) {
            throw PerformativeException::truncatedOpen();
        }

        if (!$decoded instanceof AmqpDescribedValue
            || !$decoded->descriptor instanceof ULong
            || $decoded->descriptor->value !== '16'
            || !$decoded->value instanceof AmqpList) {
            throw PerformativeException::truncatedOpen();
        }

        $fields = $decoded->value->items;

        if (count($fields) < 1 || !is_string($fields[0])) {
            throw PerformativeException::missingOpenContainerId();
        }

        return new Open(
            containerId: $fields[0],
            hostname: $this->nullableString($fields[1] ?? null),
            maxFrameSize: $this->nullableUInt($fields[2] ?? null),
            channelMax: $this->nullableUShort($fields[3] ?? null),
            idleTimeOut: $this->nullableUInt($fields[4] ?? null),
            outgoingLocales: $this->multipleSymbols($fields[5] ?? null),
            incomingLocales: $this->multipleSymbols($fields[6] ?? null),
            offeredCapabilities: $this->multipleSymbols($fields[7] ?? null),
            desiredCapabilities: $this->multipleSymbols($fields[8] ?? null),
            properties: $this->nullableMap($fields[9] ?? null),
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

    private function nullableString(mixed $value): ?string
    {
        if ($value === null || is_string($value)) {
            return $value;
        }

        throw PerformativeException::truncatedOpen();
    }

    private function nullableUInt(mixed $value): ?int
    {
        if ($value === null) {
            return null;
        }

        if ($value instanceof UInt) {
            return $value->value;
        }

        throw PerformativeException::truncatedOpen();
    }

    private function nullableUShort(mixed $value): ?int
    {
        if ($value === null) {
            return null;
        }

        if ($value instanceof UShort) {
            return $value->value;
        }

        throw PerformativeException::truncatedOpen();
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

        throw PerformativeException::truncatedOpen();
    }

    private function nullableMap(mixed $value): ?AmqpMap
    {
        if ($value === null || $value instanceof AmqpMap) {
            return $value;
        }

        throw PerformativeException::truncatedOpen();
    }
}
