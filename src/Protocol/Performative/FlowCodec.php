<?php

declare(strict_types=1);

namespace Sigbits\Amqp\Protocol\Performative;

use Sigbits\Amqp\Protocol\Type\UInt;

final class FlowCodec
{
    private const string DESCRIPTOR = "\x00\x53\x13";
    private const int DESCRIPTOR_LENGTH = 3;
    private const int CONSTRUCTOR_LIST0 = 0x45;
    private const int CONSTRUCTOR_LIST8 = 0xc0;
    private const int CONSTRUCTOR_NULL = 0x40;
    private const int CONSTRUCTOR_UINT0 = 0x43;
    private const int CONSTRUCTOR_SMALLUINT = 0x52;
    private const int CONSTRUCTOR_UINT = 0x70;

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
        [$linkCredit] = $this->decodeUInt($bytes, $cursor, $listEnd);

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
        if ($cursor >= $listEnd) {
            throw PerformativeException::truncatedFlow();
        }

        return match (ord($bytes[$cursor])) {
            self::CONSTRUCTOR_NULL, self::CONSTRUCTOR_UINT0 => $cursor + 1,
            self::CONSTRUCTOR_SMALLUINT => $cursor + 2 <= $listEnd ? $cursor + 2 : throw PerformativeException::truncatedFlow(),
            self::CONSTRUCTOR_UINT => $cursor + 5 <= $listEnd ? $cursor + 5 : throw PerformativeException::truncatedFlow(),
            default => throw PerformativeException::missingFlowLinkCreditFields(),
        };
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
        if ($cursor >= $listEnd) {
            throw PerformativeException::truncatedFlow();
        }

        $constructor = ord($bytes[$cursor]);
        ++$cursor;

        if ($constructor === self::CONSTRUCTOR_NULL) {
            return [null, $cursor];
        }

        if ($constructor === self::CONSTRUCTOR_UINT0) {
            return [0, $cursor];
        }

        if ($constructor === self::CONSTRUCTOR_SMALLUINT) {
            if ($cursor >= $listEnd) {
                throw PerformativeException::truncatedFlow();
            }

            return [ord($bytes[$cursor]), $cursor + 1];
        }

        if ($constructor !== self::CONSTRUCTOR_UINT) {
            throw PerformativeException::missingFlowLinkCreditFields();
        }

        if ($cursor + 4 > $listEnd) {
            throw PerformativeException::truncatedFlow();
        }

        return [
            (ord($bytes[$cursor]) << 24)
            | (ord($bytes[$cursor + 1]) << 16)
            | (ord($bytes[$cursor + 2]) << 8)
            | ord($bytes[$cursor + 3]),
            $cursor + 4,
        ];
    }
}
