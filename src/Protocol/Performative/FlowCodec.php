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
    private const int CONSTRUCTOR_UINT = 0x70;

    public function encode(Flow $flow): string
    {
        $fields = "\x40\x40\x40\x40"
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

        $cursor = $this->skipNull($bytes, $cursor, $listEnd);
        $cursor = $this->skipNull($bytes, $cursor, $listEnd);
        $cursor = $this->skipNull($bytes, $cursor, $listEnd);
        $cursor = $this->skipNull($bytes, $cursor, $listEnd);

        [$handle, $cursor] = $this->decodeUInt($bytes, $cursor, $listEnd);
        [$deliveryCount, $cursor] = $this->decodeUInt($bytes, $cursor, $listEnd);
        [$linkCredit] = $this->decodeUInt($bytes, $cursor, $listEnd);

        return new Flow(
            handle: $handle,
            deliveryCount: $deliveryCount,
            linkCredit: $linkCredit,
        );
    }

    private function encodeUInt(int $value): string
    {
        return chr(self::CONSTRUCTOR_UINT) . pack('N', (new UInt($value))->value);
    }

    private function skipNull(string $bytes, int $cursor, int $listEnd): int
    {
        if ($cursor >= $listEnd) {
            throw PerformativeException::truncatedFlow();
        }

        if (ord($bytes[$cursor]) !== self::CONSTRUCTOR_NULL) {
            throw PerformativeException::missingFlowLinkCreditFields();
        }

        return $cursor + 1;
    }

    /**
     * @return array{0: int, 1: int}
     */
    private function decodeUInt(string $bytes, int $cursor, int $listEnd): array
    {
        if ($cursor >= $listEnd) {
            throw PerformativeException::truncatedFlow();
        }

        if (ord($bytes[$cursor]) !== self::CONSTRUCTOR_UINT) {
            throw PerformativeException::missingFlowLinkCreditFields();
        }

        ++$cursor;

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
