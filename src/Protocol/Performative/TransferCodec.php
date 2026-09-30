<?php

declare(strict_types=1);

namespace Sigbits\Amqp\Protocol\Performative;

use Sigbits\Amqp\Protocol\Codec\DecodeException;
use Sigbits\Amqp\Protocol\Codec\ScalarReader;
use Sigbits\Amqp\Protocol\Type\UInt;

final class TransferCodec
{
    private const string DESCRIPTOR = "\x00\x53\x14";
    private const int DESCRIPTOR_LENGTH = 3;
    private const int CONSTRUCTOR_LIST0 = 0x45;
    private const int CONSTRUCTOR_LIST8 = 0xc0;
    private const int CONSTRUCTOR_NULL = 0x40;
    private const int CONSTRUCTOR_BOOL_TRUE = 0x41;
    private const int CONSTRUCTOR_BOOL_FALSE = 0x42;
    private const int CONSTRUCTOR_UINT = 0x70;
    private const int CONSTRUCTOR_VBIN8 = 0xa0;

    private readonly ScalarReader $scalarReader;

    public function __construct(?ScalarReader $scalarReader = null)
    {
        $this->scalarReader = $scalarReader ?? new ScalarReader();
    }

    public function encode(Transfer $transfer): string
    {
        $fields = $this->encodeUInt($transfer->handle)
            . $this->encodeUInt($transfer->deliveryId)
            . $this->encodeBinary($transfer->deliveryTag)
            . $this->encodeUInt($transfer->messageFormat)
            . chr(self::CONSTRUCTOR_NULL)
            . ($transfer->more ? "\x41" : "\x42");
        $listSize = 1 + strlen($fields);

        return self::DESCRIPTOR
            . chr(self::CONSTRUCTOR_LIST8)
            . chr($listSize)
            . "\x06"
            . $fields;
    }

    public function decode(string $bytes): Transfer
    {
        if (strlen($bytes) < self::DESCRIPTOR_LENGTH + 1) {
            throw PerformativeException::truncatedTransfer();
        }

        if (substr($bytes, 0, self::DESCRIPTOR_LENGTH) !== self::DESCRIPTOR) {
            throw PerformativeException::expectedTransferDescriptor();
        }

        $cursor = self::DESCRIPTOR_LENGTH;
        $constructor = ord($bytes[$cursor]);
        ++$cursor;

        if ($constructor === self::CONSTRUCTOR_LIST0) {
            throw PerformativeException::missingTransferRequiredFields();
        }

        if ($constructor !== self::CONSTRUCTOR_LIST8) {
            throw PerformativeException::truncatedTransfer();
        }

        if (strlen($bytes) < $cursor + 2) {
            throw PerformativeException::truncatedTransfer();
        }

        $listSize = ord($bytes[$cursor]);
        ++$cursor;
        $fieldCount = ord($bytes[$cursor]);
        ++$cursor;

        if ($fieldCount < 4) {
            throw PerformativeException::missingTransferRequiredFields();
        }

        $listEnd = $cursor + $listSize - 1;

        if (strlen($bytes) < $listEnd) {
            throw PerformativeException::truncatedTransfer();
        }

        [$handle, $cursor] = $this->decodeUInt($bytes, $cursor, $listEnd);
        [$deliveryId, $cursor] = $this->decodeUInt($bytes, $cursor, $listEnd);
        [$deliveryTag, $cursor] = $this->decodeBinary($bytes, $cursor, $listEnd);
        [$messageFormat, $cursor] = $this->decodeUInt($bytes, $cursor, $listEnd);
        $more = false;

        if ($fieldCount >= 5) {
            $cursor = $this->skipValue($bytes, $cursor, $listEnd);
        }

        if ($fieldCount >= 6) {
            [$more] = $this->decodeBoolean($bytes, $cursor, $listEnd);
        }

        return new Transfer(
            handle: $handle,
            deliveryId: $deliveryId,
            deliveryTag: $deliveryTag,
            messageFormat: $messageFormat,
            more: $more,
        );
    }

    private function encodeUInt(int $value): string
    {
        return chr(self::CONSTRUCTOR_UINT) . pack('N', (new UInt($value))->value);
    }

    private function encodeBinary(string $value): string
    {
        return chr(self::CONSTRUCTOR_VBIN8) . chr(strlen($value)) . $value;
    }

    /**
     * @return array{0: int, 1: int}
     */
    private function decodeUInt(string $bytes, int $cursor, int $listEnd): array
    {
        try {
            [$value, $cursor] = $this->scalarReader->readNullableUInt($bytes, $cursor, $listEnd);
        } catch (DecodeException $exception) {
            if (str_starts_with($exception->getMessage(), 'Unsupported AMQP format code')) {
                throw PerformativeException::missingTransferRequiredFields();
            }

            throw PerformativeException::truncatedTransfer();
        }

        if ($value === null) {
            throw PerformativeException::missingTransferRequiredFields();
        }

        return [$value, $cursor];
    }

    /**
     * @return array{0: string, 1: int}
     */
    private function decodeBinary(string $bytes, int $cursor, int $listEnd): array
    {
        if ($cursor >= $listEnd) {
            throw PerformativeException::truncatedTransfer();
        }

        if (ord($bytes[$cursor]) !== self::CONSTRUCTOR_VBIN8) {
            throw PerformativeException::missingTransferRequiredFields();
        }

        ++$cursor;

        if ($cursor >= $listEnd) {
            throw PerformativeException::truncatedTransfer();
        }

        $length = ord($bytes[$cursor]);
        ++$cursor;

        if ($cursor + $length > $listEnd) {
            throw PerformativeException::truncatedTransfer();
        }

        return [
            substr($bytes, $cursor, $length),
            $cursor + $length,
        ];
    }

    private function skipValue(string $bytes, int $cursor, int $listEnd): int
    {
        try {
            return $this->scalarReader->skipValue($bytes, $cursor, $listEnd);
        } catch (DecodeException $exception) {
            if (str_starts_with($exception->getMessage(), 'Unsupported AMQP format code')) {
                throw PerformativeException::missingTransferRequiredFields();
            }

            throw PerformativeException::truncatedTransfer();
        }
    }

    /**
     * @return array{0: bool, 1: int}
     */
    private function decodeBoolean(string $bytes, int $cursor, int $listEnd): array
    {
        if ($cursor >= $listEnd) {
            throw PerformativeException::truncatedTransfer();
        }

        $constructor = ord($bytes[$cursor]);

        return match ($constructor) {
            self::CONSTRUCTOR_BOOL_TRUE => [true, $cursor + 1],
            self::CONSTRUCTOR_BOOL_FALSE => [false, $cursor + 1],
            default => throw PerformativeException::missingTransferRequiredFields(),
        };
    }
}
