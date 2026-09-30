<?php

declare(strict_types=1);

namespace Sigbits\Amqp\Protocol\Performative;

use Sigbits\Amqp\Protocol\Codec\DecodeException;
use Sigbits\Amqp\Protocol\Codec\ScalarReader;
use Sigbits\Amqp\Protocol\Type\UInt;

final class FlowCodec
{
    private const string DESCRIPTOR = "\x00\x53\x13";
    private const int DESCRIPTOR_LENGTH = 3;
    private const int CONSTRUCTOR_LIST0 = 0x45;
    private const int CONSTRUCTOR_LIST8 = 0xc0;
    private const int CONSTRUCTOR_UINT = 0x70;

    private readonly ScalarReader $scalarReader;

    public function __construct(?ScalarReader $scalarReader = null)
    {
        $this->scalarReader = $scalarReader ?? new ScalarReader();
    }

    public function encode(Flow $flow): string
    {
        $fields = "\x40"
            . ($flow->incomingWindow === null ? "\x40" : $this->encodeUInt($flow->incomingWindow))
            . ($flow->nextOutgoingId === null ? "\x40" : $this->encodeUInt($flow->nextOutgoingId))
            . ($flow->outgoingWindow === null ? "\x40" : $this->encodeUInt($flow->outgoingWindow))
            . $this->encodeUInt($flow->handle)
            . $this->encodeUInt($flow->deliveryCount)
            . $this->encodeUInt($flow->linkCredit);
        $listSize = 1 + strlen($fields);

        return self::DESCRIPTOR
            . chr(self::CONSTRUCTOR_LIST8)
            . chr($listSize)
            . "\x07"
            . $fields;
    }

    public function decode(string $bytes): Flow
    {
        if (strlen($bytes) < self::DESCRIPTOR_LENGTH + 1) {
            throw PerformativeException::truncatedFlow();
        }

        if (substr($bytes, 0, self::DESCRIPTOR_LENGTH) !== self::DESCRIPTOR) {
            throw PerformativeException::expectedFlowDescriptor();
        }

        $cursor = self::DESCRIPTOR_LENGTH;
        $constructor = ord($bytes[$cursor]);
        ++$cursor;

        if ($constructor === self::CONSTRUCTOR_LIST0) {
            throw PerformativeException::missingFlowLinkCreditFields();
        }

        if ($constructor !== self::CONSTRUCTOR_LIST8) {
            throw PerformativeException::truncatedFlow();
        }

        if (strlen($bytes) < $cursor + 2) {
            throw PerformativeException::truncatedFlow();
        }

        $listSize = ord($bytes[$cursor]);
        ++$cursor;
        $fieldCount = ord($bytes[$cursor]);
        ++$cursor;

        if ($fieldCount < 7) {
            throw PerformativeException::missingFlowLinkCreditFields();
        }

        $listEnd = $cursor + $listSize - 1;

        if (strlen($bytes) < $listEnd) {
            throw PerformativeException::truncatedFlow();
        }

        $cursor = $this->skipValue($bytes, $cursor, $listEnd);
        $cursor = $this->skipValue($bytes, $cursor, $listEnd);
        $cursor = $this->skipValue($bytes, $cursor, $listEnd);
        $cursor = $this->skipValue($bytes, $cursor, $listEnd);

        [$handle, $cursor] = $this->decodeUInt($bytes, $cursor, $listEnd);
        [$deliveryCount, $cursor] = $this->decodeNullableUInt($bytes, $cursor, $listEnd);
        [$linkCredit, $cursor] = $this->decodeUInt($bytes, $cursor, $listEnd);

        for ($field = 7; $field < $fieldCount; ++$field) {
            $cursor = $this->skipValue($bytes, $cursor, $listEnd);
        }

        if ($cursor !== $listEnd) {
            throw PerformativeException::malformedFlow();
        }

        return new Flow(
            handle: $handle,
            deliveryCount: $deliveryCount ?? 0,
            linkCredit: $linkCredit,
        );
    }

    private function encodeUInt(int $value): string
    {
        return chr(self::CONSTRUCTOR_UINT) . pack('N', (new UInt($value))->value);
    }

    private function skipValue(string $bytes, int $cursor, int $listEnd): int
    {
        try {
            return $this->scalarReader->skipValue($bytes, $cursor, $listEnd);
        } catch (DecodeException $exception) {
            if (str_starts_with($exception->getMessage(), 'Unsupported AMQP format code')) {
                throw PerformativeException::missingFlowLinkCreditFields();
            }

            throw PerformativeException::truncatedFlow();
        }
    }

    /**
     * @return array{0: int, 1: int}
     */
    private function decodeUInt(string $bytes, int $cursor, int $listEnd): array
    {
        [$value, $cursor] = $this->decodeNullableUInt($bytes, $cursor, $listEnd);

        if ($value === null) {
            throw PerformativeException::missingFlowLinkCreditFields();
        }

        return [$value, $cursor];
    }

    /**
     * @return array{0: int|null, 1: int}
     */
    private function decodeNullableUInt(string $bytes, int $cursor, int $listEnd): array
    {
        try {
            return $this->scalarReader->readNullableUInt($bytes, $cursor, $listEnd);
        } catch (DecodeException $exception) {
            if (str_starts_with($exception->getMessage(), 'Unsupported AMQP format code')) {
                throw PerformativeException::missingFlowLinkCreditFields();
            }

            throw PerformativeException::truncatedFlow();
        }
    }
}
