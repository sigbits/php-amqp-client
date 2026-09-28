<?php

declare(strict_types=1);

namespace Sigbits\Amqp\Protocol\Performative;

use Sigbits\Amqp\Protocol\Type\UInt;
use Sigbits\Amqp\Protocol\Type\UShort;

final class BeginCodec
{
    private const string DESCRIPTOR = "\x00\x53\x11";
    private const int DESCRIPTOR_LENGTH = 3;
    private const int CONSTRUCTOR_LIST0 = 0x45;
    private const int CONSTRUCTOR_LIST8 = 0xc0;
    private const int CONSTRUCTOR_NULL = 0x40;
    private const int CONSTRUCTOR_USHORT = 0x60;
    private const int CONSTRUCTOR_UINT = 0x70;

    public function encode(Begin $begin): string
    {
        $fields = ($begin->remoteChannel === null ? "\x40" : $this->encodeUShort($begin->remoteChannel))
            . $this->encodeUInt($begin->nextOutgoingId)
            . $this->encodeUInt($begin->incomingWindow)
            . $this->encodeUInt($begin->outgoingWindow);
        $listSize = 1 + strlen($fields);

        return self::DESCRIPTOR
            . chr(self::CONSTRUCTOR_LIST8)
            . chr($listSize)
            . "\x04"
            . $fields;
    }

    public function decode(string $bytes): Begin
    {
        if (strlen($bytes) < self::DESCRIPTOR_LENGTH + 1) {
            throw PerformativeException::truncatedBegin();
        }

        if (substr($bytes, 0, self::DESCRIPTOR_LENGTH) !== self::DESCRIPTOR) {
            throw PerformativeException::expectedBeginDescriptor();
        }

        $cursor = self::DESCRIPTOR_LENGTH;
        $constructor = ord($bytes[$cursor]);
        ++$cursor;

        if ($constructor === self::CONSTRUCTOR_LIST0) {
            throw PerformativeException::missingBeginRequiredFields();
        }

        if ($constructor !== self::CONSTRUCTOR_LIST8) {
            throw PerformativeException::truncatedBegin();
        }

        if (strlen($bytes) < $cursor + 2) {
            throw PerformativeException::truncatedBegin();
        }

        $listSize = ord($bytes[$cursor]);
        ++$cursor;
        $fieldCount = ord($bytes[$cursor]);
        ++$cursor;

        if ($fieldCount < 4) {
            throw PerformativeException::missingBeginRequiredFields();
        }

        $listEnd = $cursor + $listSize - 1;

        if (strlen($bytes) < $listEnd) {
            throw PerformativeException::truncatedBegin();
        }

        [$remoteChannel, $cursor] = $this->decodeNullableUShort($bytes, $cursor, $listEnd);
        [$nextOutgoingId, $cursor] = $this->decodeUInt($bytes, $cursor, $listEnd);
        [$incomingWindow, $cursor] = $this->decodeUInt($bytes, $cursor, $listEnd);
        [$outgoingWindow] = $this->decodeUInt($bytes, $cursor, $listEnd);

        return new Begin(
            remoteChannel: $remoteChannel,
            nextOutgoingId: $nextOutgoingId,
            incomingWindow: $incomingWindow,
            outgoingWindow: $outgoingWindow,
        );
    }

    private function encodeUShort(int $value): string
    {
        return chr(self::CONSTRUCTOR_USHORT) . pack('n', (new UShort($value))->value);
    }

    private function encodeUInt(int $value): string
    {
        return chr(self::CONSTRUCTOR_UINT) . pack('N', (new UInt($value))->value);
    }

    /**
     * @return array{0: int|null, 1: int}
     */
    private function decodeNullableUShort(string $bytes, int $cursor, int $listEnd): array
    {
        if ($cursor >= $listEnd) {
            throw PerformativeException::truncatedBegin();
        }

        $constructor = ord($bytes[$cursor]);
        ++$cursor;

        if ($constructor === self::CONSTRUCTOR_NULL) {
            return [null, $cursor];
        }

        if ($constructor !== self::CONSTRUCTOR_USHORT) {
            throw PerformativeException::missingBeginRequiredFields();
        }

        if ($cursor + 2 > $listEnd) {
            throw PerformativeException::truncatedBegin();
        }

        return [
            (ord($bytes[$cursor]) << 8) | ord($bytes[$cursor + 1]),
            $cursor + 2,
        ];
    }

    /**
     * @return array{0: int, 1: int}
     */
    private function decodeUInt(string $bytes, int $cursor, int $listEnd): array
    {
        if ($cursor >= $listEnd) {
            throw PerformativeException::truncatedBegin();
        }

        if (ord($bytes[$cursor]) !== self::CONSTRUCTOR_UINT) {
            throw PerformativeException::missingBeginRequiredFields();
        }

        ++$cursor;

        if ($cursor + 4 > $listEnd) {
            throw PerformativeException::truncatedBegin();
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
