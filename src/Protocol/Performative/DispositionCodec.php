<?php

declare(strict_types=1);

namespace Sigbits\Amqp\Protocol\Performative;

use Sigbits\Amqp\Protocol\Codec\DecodeException;
use Sigbits\Amqp\Protocol\Codec\ScalarReader;
use Sigbits\Amqp\Protocol\Type\UInt;

final class DispositionCodec
{
    private const string DESCRIPTOR = "\x00\x53\x15";
    private const string ACCEPTED_DESCRIPTOR = "\x00\x53\x24";
    private const string REJECTED_DESCRIPTOR = "\x00\x53\x25";
    private const string RELEASED_DESCRIPTOR = "\x00\x53\x26";
    private const int DESCRIPTOR_LENGTH = 3;
    private const int CONSTRUCTOR_LIST0 = 0x45;
    private const int CONSTRUCTOR_LIST8 = 0xc0;
    private const int CONSTRUCTOR_NULL = 0x40;
    private const int CONSTRUCTOR_BOOL_TRUE = 0x41;
    private const int CONSTRUCTOR_UINT = 0x70;

    private readonly ScalarReader $scalarReader;

    public function __construct(?ScalarReader $scalarReader = null)
    {
        $this->scalarReader = $scalarReader ?? new ScalarReader();
    }

    public function encode(Disposition $disposition): string
    {
        $fields = "\x41"
            . $this->encodeUInt($disposition->deliveryId)
            . "\x40"
            . "\x41"
            . $this->encodeOutcome($disposition->outcome);
        $listSize = 1 + strlen($fields);

        return self::DESCRIPTOR
            . chr(self::CONSTRUCTOR_LIST8)
            . chr($listSize)
            . "\x05"
            . $fields;
    }

    public function decode(string $bytes): Disposition
    {
        if (strlen($bytes) < self::DESCRIPTOR_LENGTH + 1) {
            throw PerformativeException::truncatedDisposition();
        }

        if (substr($bytes, 0, self::DESCRIPTOR_LENGTH) !== self::DESCRIPTOR) {
            throw PerformativeException::expectedDispositionDescriptor();
        }

        $cursor = self::DESCRIPTOR_LENGTH;
        $constructor = ord($bytes[$cursor]);
        ++$cursor;

        if ($constructor === self::CONSTRUCTOR_LIST0) {
            throw PerformativeException::missingDispositionRequiredFields();
        }

        if ($constructor !== self::CONSTRUCTOR_LIST8) {
            throw PerformativeException::truncatedDisposition();
        }

        if (strlen($bytes) < $cursor + 2) {
            throw PerformativeException::truncatedDisposition();
        }

        $listSize = ord($bytes[$cursor]);
        ++$cursor;
        $fieldCount = ord($bytes[$cursor]);
        ++$cursor;

        if ($fieldCount < 5) {
            throw PerformativeException::missingDispositionRequiredFields();
        }

        $listEnd = $cursor + $listSize - 1;

        if (strlen($bytes) < $listEnd) {
            throw PerformativeException::truncatedDisposition();
        }

        $cursor = $this->decodeReceiverRole($bytes, $cursor, $listEnd);
        [$deliveryId, $cursor] = $this->decodeUInt($bytes, $cursor, $listEnd);
        $cursor = $this->skipNull($bytes, $cursor, $listEnd);
        $cursor = $this->decodeSettled($bytes, $cursor, $listEnd);
        [$outcome, $cursor] = $this->decodeOutcome($bytes, $cursor, $listEnd);

        for ($field = 5; $field < $fieldCount; ++$field) {
            $cursor = $this->skipValue($bytes, $cursor, $listEnd);
        }

        if ($cursor !== $listEnd) {
            throw PerformativeException::malformedDisposition();
        }

        return new Disposition(
            deliveryId: $deliveryId,
            outcome: $outcome,
        );
    }

    private function encodeUInt(int $value): string
    {
        return chr(self::CONSTRUCTOR_UINT) . pack('N', (new UInt($value))->value);
    }

    private function encodeOutcome(SettlementOutcome $outcome): string
    {
        return match ($outcome) {
            SettlementOutcome::Accepted => self::ACCEPTED_DESCRIPTOR . chr(self::CONSTRUCTOR_LIST0),
            SettlementOutcome::Released => self::RELEASED_DESCRIPTOR . chr(self::CONSTRUCTOR_LIST0),
            SettlementOutcome::Rejected => self::REJECTED_DESCRIPTOR . chr(self::CONSTRUCTOR_LIST0),
        };
    }

    private function decodeReceiverRole(string $bytes, int $cursor, int $listEnd): int
    {
        if ($cursor >= $listEnd) {
            throw PerformativeException::truncatedDisposition();
        }

        if (ord($bytes[$cursor]) !== self::CONSTRUCTOR_BOOL_TRUE) {
            throw PerformativeException::missingDispositionRequiredFields();
        }

        return $cursor + 1;
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
                throw PerformativeException::missingDispositionRequiredFields();
            }

            throw PerformativeException::truncatedDisposition();
        }
    }

    private function skipNull(string $bytes, int $cursor, int $listEnd): int
    {
        if ($cursor >= $listEnd) {
            throw PerformativeException::truncatedDisposition();
        }

        if (ord($bytes[$cursor]) !== self::CONSTRUCTOR_NULL) {
            throw PerformativeException::missingDispositionRequiredFields();
        }

        return $cursor + 1;
    }

    private function skipValue(string $bytes, int $cursor, int $listEnd): int
    {
        try {
            return $this->scalarReader->skipValue($bytes, $cursor, $listEnd);
        } catch (DecodeException $exception) {
            if (str_starts_with($exception->getMessage(), 'Unsupported AMQP format code')) {
                throw PerformativeException::missingDispositionRequiredFields();
            }

            throw PerformativeException::truncatedDisposition();
        }
    }

    private function decodeSettled(string $bytes, int $cursor, int $listEnd): int
    {
        if ($cursor >= $listEnd) {
            throw PerformativeException::truncatedDisposition();
        }

        if (ord($bytes[$cursor]) !== self::CONSTRUCTOR_BOOL_TRUE) {
            throw PerformativeException::missingDispositionRequiredFields();
        }

        return $cursor + 1;
    }

    /**
     * @return array{0: SettlementOutcome, 1: int}
     */
    private function decodeOutcome(string $bytes, int $cursor, int $listEnd): array
    {
        if ($cursor + self::DESCRIPTOR_LENGTH + 1 > $listEnd) {
            throw PerformativeException::truncatedDisposition();
        }

        $descriptor = substr($bytes, $cursor, self::DESCRIPTOR_LENGTH);
        $cursor += self::DESCRIPTOR_LENGTH;

        if (ord($bytes[$cursor]) !== self::CONSTRUCTOR_LIST0) {
            throw PerformativeException::missingDispositionRequiredFields();
        }

        ++$cursor;

        return match ($descriptor) {
            self::ACCEPTED_DESCRIPTOR => [SettlementOutcome::Accepted, $cursor],
            self::RELEASED_DESCRIPTOR => [SettlementOutcome::Released, $cursor],
            self::REJECTED_DESCRIPTOR => [SettlementOutcome::Rejected, $cursor],
            default => throw PerformativeException::missingDispositionRequiredFields(),
        };
    }
}
