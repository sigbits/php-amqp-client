<?php

declare(strict_types=1);

namespace Sigbits\Amqp\Protocol\Sasl;

use InvalidArgumentException;

final class SaslMechanismsCodec
{
    private const string DESCRIPTOR = "\x00\x53\x40";
    private const int DESCRIPTOR_LENGTH = 3;
    private const int CONSTRUCTOR_LIST0 = 0x45;
    private const int CONSTRUCTOR_LIST8 = 0xc0;
    private const int CONSTRUCTOR_ARRAY8 = 0xe0;
    private const int CONSTRUCTOR_SYMBOL8 = 0xa3;

    public function encode(SaslMechanisms $mechanisms): string
    {
        $fields = $this->encodeSymbolArray($mechanisms->serverMechanisms);
        $listSize = 1 + strlen($fields);

        return self::DESCRIPTOR
            . chr(self::CONSTRUCTOR_LIST8)
            . chr($listSize)
            . "\x01"
            . $fields;
    }

    public function decode(string $bytes): SaslMechanisms
    {
        if (strlen($bytes) < self::DESCRIPTOR_LENGTH + 1) {
            throw SaslException::truncatedMechanisms();
        }

        if (substr($bytes, 0, self::DESCRIPTOR_LENGTH) !== self::DESCRIPTOR) {
            throw SaslException::expectedMechanismsDescriptor();
        }

        $cursor = self::DESCRIPTOR_LENGTH;
        $constructor = ord($bytes[$cursor]);
        ++$cursor;

        if ($constructor === self::CONSTRUCTOR_LIST0) {
            throw SaslException::missingServerMechanisms();
        }

        if ($constructor !== self::CONSTRUCTOR_LIST8) {
            throw SaslException::truncatedMechanisms();
        }

        if (strlen($bytes) < $cursor + 2) {
            throw SaslException::truncatedMechanisms();
        }

        $listSize = ord($bytes[$cursor]);
        ++$cursor;
        $fieldCount = ord($bytes[$cursor]);
        ++$cursor;

        if ($fieldCount < 1) {
            throw SaslException::missingServerMechanisms();
        }

        $listEnd = $cursor + $listSize - 1;

        if (strlen($bytes) < $listEnd) {
            throw SaslException::truncatedMechanisms();
        }

        [$serverMechanisms] = $this->decodeSymbolArray($bytes, $cursor, $listEnd);

        return new SaslMechanisms($serverMechanisms);
    }

    /**
     * @param list<string> $values
     */
    private function encodeSymbolArray(array $values): string
    {
        $symbols = '';

        foreach ($values as $value) {
            $symbols .= chr(strlen($value)) . $value;
        }

        $arraySize = 2 + strlen($symbols);

        if ($arraySize > 255) {
            throw new InvalidArgumentException('AMQP SASL server mechanisms must fit in array8 encoding.');
        }

        return chr(self::CONSTRUCTOR_ARRAY8)
            . chr($arraySize)
            . chr(count($values))
            . chr(self::CONSTRUCTOR_SYMBOL8)
            . $symbols;
    }

    /**
     * @return array{0: non-empty-list<string>, 1: int}
     */
    private function decodeSymbolArray(string $bytes, int $cursor, int $listEnd): array
    {
        if ($cursor >= $listEnd) {
            throw SaslException::truncatedMechanisms();
        }

        if (ord($bytes[$cursor]) !== self::CONSTRUCTOR_ARRAY8) {
            throw SaslException::missingServerMechanisms();
        }

        ++$cursor;

        if ($cursor >= $listEnd) {
            throw SaslException::truncatedMechanisms();
        }

        $arraySize = ord($bytes[$cursor]);
        ++$cursor;
        $arrayEnd = $cursor + $arraySize;

        if ($arrayEnd > $listEnd) {
            throw SaslException::truncatedMechanisms();
        }

        if ($cursor + 2 > $arrayEnd) {
            throw SaslException::truncatedMechanisms();
        }

        $count = ord($bytes[$cursor]);
        ++$cursor;

        if ($count < 1) {
            throw SaslException::missingServerMechanisms();
        }

        if (ord($bytes[$cursor]) !== self::CONSTRUCTOR_SYMBOL8) {
            throw SaslException::missingServerMechanisms();
        }

        ++$cursor;

        $values = [];

        for ($i = 0; $i < $count; ++$i) {
            if ($cursor >= $arrayEnd) {
                throw SaslException::truncatedMechanisms();
            }

            $length = ord($bytes[$cursor]);
            ++$cursor;

            if ($cursor + $length > $arrayEnd) {
                throw SaslException::truncatedMechanisms();
            }

            $values[] = substr($bytes, $cursor, $length);
            $cursor += $length;
        }

        return [
            $values,
            $cursor,
        ];
    }
}
