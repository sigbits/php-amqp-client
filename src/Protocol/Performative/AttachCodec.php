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

final class AttachCodec
{
    private const string DESCRIPTOR = "\x00\x53\x12";
    private const int DESCRIPTOR_LENGTH = 3;
    private const int CONSTRUCTOR_LIST0 = 0x45;
    private const int CONSTRUCTOR_LIST8 = 0xc0;
    private const int CONSTRUCTOR_STR8 = 0xa1;
    private const int CONSTRUCTOR_BOOL_TRUE = 0x41;
    private const int CONSTRUCTOR_BOOL_FALSE = 0x42;
    private const int CONSTRUCTOR_NULL = 0x40;
    private const int CONSTRUCTOR_UINT0 = 0x43;
    private const int CONSTRUCTOR_SMALLUINT = 0x52;
    private const int CONSTRUCTOR_BOOL = 0x56;
    private const int CONSTRUCTOR_UINT = 0x70;

    private readonly ScalarReader $scalarReader;
    private readonly Decoder $decoder;
    private readonly Encoder $encoder;

    public function __construct(
        ?ScalarReader $scalarReader = null,
        ?Decoder $decoder = null,
        ?Encoder $encoder = null,
    ) {
        $this->scalarReader = $scalarReader ?? new ScalarReader();
        $this->decoder = $decoder ?? new Decoder();
        $this->encoder = $encoder ?? new Encoder();
    }

    public function encode(Attach $attach): string
    {
        $fields = $this->encodeString($attach->name)
            . $this->encodeUInt($attach->handle)
            . ($attach->role === LinkRole::Receiver ? "\x41" : "\x42");
        $fieldCount = 3;

        if ($attach->source !== null || $attach->target !== null || $attach->initialDeliveryCount !== null) {
            $fields .= chr(self::CONSTRUCTOR_NULL)
                . chr(self::CONSTRUCTOR_NULL)
                . ($attach->source !== null ? $this->encodeSource($attach->source) : chr(self::CONSTRUCTOR_NULL))
                . ($attach->target !== null ? $this->encodeTarget($attach->target) : chr(self::CONSTRUCTOR_NULL));
            $fieldCount = 7;
        }

        if ($attach->initialDeliveryCount !== null) {
            $fields .= chr(self::CONSTRUCTOR_NULL)
                . chr(self::CONSTRUCTOR_NULL)
                . $this->encodeCompactUInt($attach->initialDeliveryCount);
            $fieldCount = 10;
        }

        $listSize = 1 + strlen($fields);

        return self::DESCRIPTOR
            . chr(self::CONSTRUCTOR_LIST8)
            . chr($listSize)
            . chr($fieldCount)
            . $fields;
    }

    public function decode(string $bytes): Attach
    {
        if (strlen($bytes) < self::DESCRIPTOR_LENGTH + 1) {
            throw PerformativeException::truncatedAttach();
        }

        if (substr($bytes, 0, self::DESCRIPTOR_LENGTH) !== self::DESCRIPTOR) {
            throw PerformativeException::expectedAttachDescriptor();
        }

        $cursor = self::DESCRIPTOR_LENGTH;
        $constructor = ord($bytes[$cursor]);
        ++$cursor;

        if ($constructor === self::CONSTRUCTOR_LIST0) {
            throw PerformativeException::missingAttachRequiredFields();
        }

        if ($constructor !== self::CONSTRUCTOR_LIST8) {
            throw PerformativeException::truncatedAttach();
        }

        if (strlen($bytes) < $cursor + 2) {
            throw PerformativeException::truncatedAttach();
        }

        $listSize = ord($bytes[$cursor]);
        ++$cursor;
        $fieldCount = ord($bytes[$cursor]);
        ++$cursor;

        if ($fieldCount < 3) {
            throw PerformativeException::missingAttachRequiredFields();
        }

        $listEnd = $cursor + $listSize - 1;

        if (strlen($bytes) < $listEnd) {
            throw PerformativeException::truncatedAttach();
        }

        [$name, $cursor] = $this->decodeString($bytes, $cursor, $listEnd);
        [$handle, $cursor] = $this->decodeUInt($bytes, $cursor, $listEnd);
        [$role, $cursor] = $this->decodeRole($bytes, $cursor, $listEnd);
        $source = null;
        $target = null;

        if ($fieldCount >= 4) {
            $cursor = $this->skipValue($bytes, $cursor, $listEnd);
        }

        if ($fieldCount >= 5) {
            $cursor = $this->skipValue($bytes, $cursor, $listEnd);
        }

        if ($fieldCount >= 6) {
            [$source, $cursor] = $this->decodeSource($bytes, $cursor, $listEnd);
        }

        if ($fieldCount >= 7) {
            [$target, $cursor] = $this->decodeTarget($bytes, $cursor, $listEnd);
        }

        for ($field = 7; $field < $fieldCount; ++$field) {
            $cursor = $this->skipValue($bytes, $cursor, $listEnd);
        }

        if ($cursor !== $listEnd) {
            throw PerformativeException::truncatedAttach();
        }

        return new Attach(
            name: $name,
            handle: $handle,
            role: $role,
            source: $source,
            target: $target,
        );
    }

    private function encodeString(string $value): string
    {
        return chr(self::CONSTRUCTOR_STR8) . chr(strlen($value)) . $value;
    }

    private function encodeUInt(int $value): string
    {
        return chr(self::CONSTRUCTOR_UINT) . pack('N', (new UInt($value))->value);
    }

    private function encodeCompactUInt(int $value): string
    {
        new UInt($value);

        if ($value === 0) {
            return chr(self::CONSTRUCTOR_UINT0);
        }

        if ($value <= 255) {
            return chr(self::CONSTRUCTOR_SMALLUINT) . chr($value);
        }

        return $this->encodeUInt($value);
    }

    private function encodeSource(Source $source): string
    {
        return "\x00\x53\x28" . $this->encoder->encode(new AmqpList($this->sourceFields($source)));
    }

    private function encodeTarget(Target $target): string
    {
        return "\x00\x53\x29" . $this->encoder->encode(new AmqpList($this->targetFields($target)));
    }

    /**
     * @return array{0: string, 1: int}
     */
    private function decodeString(string $bytes, int $cursor, int $listEnd): array
    {
        if ($cursor >= $listEnd) {
            throw PerformativeException::truncatedAttach();
        }

        if (ord($bytes[$cursor]) !== self::CONSTRUCTOR_STR8) {
            throw PerformativeException::missingAttachRequiredFields();
        }

        ++$cursor;

        if ($cursor >= $listEnd) {
            throw PerformativeException::truncatedAttach();
        }

        $length = ord($bytes[$cursor]);
        ++$cursor;

        if ($cursor + $length > $listEnd) {
            throw PerformativeException::truncatedAttach();
        }

        return [
            substr($bytes, $cursor, $length),
            $cursor + $length,
        ];
    }

    /**
     * @return array{0: int, 1: int}
     */
    private function decodeUInt(string $bytes, int $cursor, int $listEnd): array
    {
        try {
            return $this->scalarReader->readUInt($bytes, $cursor, $listEnd);
        } catch (DecodeException $exception) {
            if (str_starts_with($exception->getMessage(), 'Unsupported AMQP format code')) {
                throw PerformativeException::missingAttachRequiredFields();
            }

            throw PerformativeException::truncatedAttach();
        }
    }

    /**
     * @return array{0: LinkRole, 1: int}
     */
    private function decodeRole(string $bytes, int $cursor, int $listEnd): array
    {
        if ($cursor >= $listEnd) {
            throw PerformativeException::truncatedAttach();
        }

        $constructor = ord($bytes[$cursor]);

        return match ($constructor) {
            self::CONSTRUCTOR_BOOL_FALSE => [LinkRole::Sender, $cursor + 1],
            self::CONSTRUCTOR_BOOL_TRUE => [LinkRole::Receiver, $cursor + 1],
            self::CONSTRUCTOR_BOOL => $this->decodeBooleanRole($bytes, $cursor, $listEnd),
            default => throw PerformativeException::missingAttachRequiredFields(),
        };
    }

    /**
     * @return array{0: LinkRole, 1: int}
     */
    private function decodeBooleanRole(string $bytes, int $cursor, int $listEnd): array
    {
        ++$cursor;

        if ($cursor >= $listEnd) {
            throw PerformativeException::truncatedAttach();
        }

        return [
            ord($bytes[$cursor]) === 0 ? LinkRole::Sender : LinkRole::Receiver,
            $cursor + 1,
        ];
    }

    /**
     * @return array{0: Source|null, 1: int}
     */
    private function decodeSource(string $bytes, int $cursor, int $listEnd): array
    {
        [$value, $cursor] = $this->decodeNullableTerminus($bytes, $cursor, $listEnd);

        if ($value === null) {
            return [null, $cursor];
        }

        if (!$value->descriptor instanceof ULong || $value->descriptor->value !== '40') {
            throw PerformativeException::truncatedAttach();
        }

        return [$this->sourceFromTerminus($value), $cursor];
    }

    /**
     * @return array{0: Target|null, 1: int}
     */
    private function decodeTarget(string $bytes, int $cursor, int $listEnd): array
    {
        [$value, $cursor] = $this->decodeNullableTerminus($bytes, $cursor, $listEnd);

        if ($value === null) {
            return [null, $cursor];
        }

        if (!$value->descriptor instanceof ULong || $value->descriptor->value !== '41') {
            throw PerformativeException::truncatedAttach();
        }

        return [$this->targetFromTerminus($value), $cursor];
    }

    /**
     * @return array{0: AmqpDescribedValue|null, 1: int}
     */
    private function decodeNullableTerminus(string $bytes, int $cursor, int $listEnd): array
    {
        if ($cursor >= $listEnd) {
            throw PerformativeException::truncatedAttach();
        }

        if (ord($bytes[$cursor]) === self::CONSTRUCTOR_NULL) {
            return [null, $cursor + 1];
        }

        try {
            [$value, $consumed] = $this->decoder->decodeWithConsumed(substr($bytes, $cursor, $listEnd - $cursor));
        } catch (DecodeException|InvalidArgumentException) {
            throw PerformativeException::truncatedAttach();
        }

        if (!$value instanceof AmqpDescribedValue) {
            throw PerformativeException::truncatedAttach();
        }

        return [$value, $cursor + $consumed];
    }

    /**
     * @return list<mixed>
     */
    private function sourceFields(Source $source): array
    {
        return $this->trimTerminusFields([
            $source->address,
            $source->durable === null ? null : new UInt($source->durable),
            $source->expiryPolicy === null ? null : new Symbol($source->expiryPolicy),
            $source->timeout === null ? null : new UInt($source->timeout),
            $source->dynamic,
            $source->dynamicNodeProperties,
            $source->distributionMode === null ? null : new Symbol($source->distributionMode),
            null,
            null,
            $this->encodeMultipleSymbols($source->outcomes),
            $this->encodeMultipleSymbols($source->capabilities),
        ]);
    }

    /**
     * @return list<mixed>
     */
    private function targetFields(Target $target): array
    {
        return $this->trimTerminusFields([
            $target->address,
            $target->durable === null ? null : new UInt($target->durable),
            $target->expiryPolicy === null ? null : new Symbol($target->expiryPolicy),
            $target->timeout === null ? null : new UInt($target->timeout),
            $target->dynamic,
            $target->dynamicNodeProperties,
            $this->encodeMultipleSymbols($target->capabilities),
        ]);
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

    /**
     * @param list<mixed> $fields
     *
     * @return list<mixed>
     */
    private function trimTerminusFields(array $fields): array
    {
        while ($fields !== [] && end($fields) === null) {
            array_pop($fields);
        }

        return $fields;
    }

    private function sourceFromTerminus(AmqpDescribedValue $value): Source
    {
        $fields = $this->terminusFields($value);

        return new Source(
            address: $this->nullableString($fields[0] ?? null),
            durable: $this->nullableUInt($fields[1] ?? null),
            expiryPolicy: $this->nullableSymbol($fields[2] ?? null),
            timeout: $this->nullableUInt($fields[3] ?? null),
            dynamic: $this->nullableBool($fields[4] ?? null),
            dynamicNodeProperties: $this->nullableMap($fields[5] ?? null),
            distributionMode: $this->nullableSymbol($fields[6] ?? null),
            outcomes: $this->multipleSymbols($fields[9] ?? null),
            capabilities: $this->multipleSymbols($fields[10] ?? null),
        );
    }

    private function targetFromTerminus(AmqpDescribedValue $value): Target
    {
        $fields = $this->terminusFields($value);

        return new Target(
            address: $this->nullableString($fields[0] ?? null),
            durable: $this->nullableUInt($fields[1] ?? null),
            expiryPolicy: $this->nullableSymbol($fields[2] ?? null),
            timeout: $this->nullableUInt($fields[3] ?? null),
            dynamic: $this->nullableBool($fields[4] ?? null),
            dynamicNodeProperties: $this->nullableMap($fields[5] ?? null),
            capabilities: $this->multipleSymbols($fields[6] ?? null),
        );
    }

    /**
     * @return list<mixed>
     */
    private function terminusFields(AmqpDescribedValue $value): array
    {
        if ($value->value instanceof AmqpList && $value->value->items === []) {
            return [];
        }

        if (!$value->value instanceof AmqpList) {
            throw PerformativeException::truncatedAttach();
        }

        return $value->value->items;
    }

    private function nullableString(mixed $value): ?string
    {
        if ($value === null || is_string($value)) {
            return $value;
        }

        throw PerformativeException::truncatedAttach();
    }

    private function nullableUInt(mixed $value): ?int
    {
        if ($value === null) {
            return null;
        }

        if ($value instanceof UInt) {
            return $value->value;
        }

        throw PerformativeException::truncatedAttach();
    }

    private function nullableSymbol(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        if ($value instanceof Symbol) {
            return $value->value;
        }

        throw PerformativeException::truncatedAttach();
    }

    private function nullableBool(mixed $value): ?bool
    {
        if ($value === null || is_bool($value)) {
            return $value;
        }

        throw PerformativeException::truncatedAttach();
    }

    private function nullableMap(mixed $value): ?AmqpMap
    {
        if ($value === null || $value instanceof AmqpMap) {
            return $value;
        }

        throw PerformativeException::truncatedAttach();
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

        throw PerformativeException::truncatedAttach();
    }

    private function skipValue(string $bytes, int $cursor, int $listEnd): int
    {
        if ($cursor >= $listEnd) {
            throw PerformativeException::truncatedAttach();
        }

        try {
            [, $consumed] = $this->decoder->decodeWithConsumed(substr($bytes, $cursor, $listEnd - $cursor));
        } catch (DecodeException|InvalidArgumentException) {
            throw PerformativeException::truncatedAttach();
        }

        return $cursor + $consumed;
    }
}
