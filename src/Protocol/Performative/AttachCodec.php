<?php

declare(strict_types=1);

namespace Sigbits\Amqp\Protocol\Performative;

use InvalidArgumentException;
use Sigbits\Amqp\Protocol\Codec\DecodeException;
use Sigbits\Amqp\Protocol\Codec\Decoder;
use Sigbits\Amqp\Protocol\Codec\ScalarReader;
use Sigbits\Amqp\Protocol\Type\AmqpDescribedValue;
use Sigbits\Amqp\Protocol\Type\AmqpList;
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
    private const string SOURCE_DESCRIPTOR = "\x00\x53\x28";
    private const string TARGET_DESCRIPTOR = "\x00\x53\x29";

    private readonly ScalarReader $scalarReader;
    private readonly Decoder $decoder;

    public function __construct(?ScalarReader $scalarReader = null, ?Decoder $decoder = null)
    {
        $this->scalarReader = $scalarReader ?? new ScalarReader();
        $this->decoder = $decoder ?? new Decoder();
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
        if ($source->address === null) {
            return self::SOURCE_DESCRIPTOR . chr(self::CONSTRUCTOR_LIST0);
        }

        return self::SOURCE_DESCRIPTOR
            . chr(self::CONSTRUCTOR_LIST8)
            . chr(1 + strlen($this->encodeString($source->address)))
            . "\x01"
            . $this->encodeString($source->address);
    }

    private function encodeTarget(Target $target): string
    {
        if ($target->address === null) {
            return self::TARGET_DESCRIPTOR . chr(self::CONSTRUCTOR_LIST0);
        }

        return self::TARGET_DESCRIPTOR
            . chr(self::CONSTRUCTOR_LIST8)
            . chr(1 + strlen($this->encodeString($target->address)))
            . "\x01"
            . $this->encodeString($target->address);
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

        return [new Source($this->terminusAddress($value)), $cursor];
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

        return [new Target($this->terminusAddress($value)), $cursor];
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

    private function terminusAddress(AmqpDescribedValue $value): ?string
    {
        if ($value->value instanceof AmqpList && $value->value->items === []) {
            return null;
        }

        if (!$value->value instanceof AmqpList) {
            throw PerformativeException::truncatedAttach();
        }

        $address = $value->value->items[0] ?? null;

        if ($address !== null && !is_string($address)) {
            throw PerformativeException::truncatedAttach();
        }

        return $address;
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
