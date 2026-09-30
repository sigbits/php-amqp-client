<?php

declare(strict_types=1);

namespace Sigbits\Amqp\Protocol\Sasl;

final class SaslOutcomeCodec
{
    private const string DESCRIPTOR = "\x00\x53\x44";
    private const int DESCRIPTOR_LENGTH = 3;
    private const int CONSTRUCTOR_LIST0 = 0x45;
    private const int CONSTRUCTOR_LIST8 = 0xc0;
    private const int CONSTRUCTOR_NULL = 0x40;
    private const int CONSTRUCTOR_UBYTE = 0x50;
    private const int CONSTRUCTOR_VBIN8 = 0xa0;

    public function encode(SaslOutcome $outcome): string
    {
        $fields = chr(self::CONSTRUCTOR_UBYTE) . chr($outcome->code->value);
        $fieldCount = 1;

        if ($outcome->additionalData !== null) {
            $fields .= $this->encodeBinary($outcome->additionalData);
            $fieldCount = 2;
        }

        $listSize = 1 + strlen($fields);

        return self::DESCRIPTOR
            . chr(self::CONSTRUCTOR_LIST8)
            . chr($listSize)
            . chr($fieldCount)
            . $fields;
    }

    public function decode(string $bytes): SaslOutcome
    {
        if (strlen($bytes) < self::DESCRIPTOR_LENGTH + 1) {
            throw SaslException::truncatedOutcome();
        }

        if (substr($bytes, 0, self::DESCRIPTOR_LENGTH) !== self::DESCRIPTOR) {
            throw SaslException::expectedOutcomeDescriptor();
        }

        $cursor = self::DESCRIPTOR_LENGTH;
        $constructor = ord($bytes[$cursor]);
        ++$cursor;

        if ($constructor === self::CONSTRUCTOR_LIST0) {
            throw SaslException::missingOutcomeCode();
        }

        if ($constructor !== self::CONSTRUCTOR_LIST8) {
            throw SaslException::truncatedOutcome();
        }

        if (strlen($bytes) < $cursor + 2) {
            throw SaslException::truncatedOutcome();
        }

        $listSize = ord($bytes[$cursor]);
        ++$cursor;
        $fieldCount = ord($bytes[$cursor]);
        ++$cursor;

        if ($fieldCount < 1) {
            throw SaslException::missingOutcomeCode();
        }

        $listEnd = $cursor + $listSize - 1;

        if (strlen($bytes) < $listEnd) {
            throw SaslException::truncatedOutcome();
        }

        [$code, $cursor] = $this->decodeCode($bytes, $cursor, $listEnd);
        $additionalData = null;

        if ($fieldCount >= 2) {
            [$additionalData] = $this->decodeNullableBinary($bytes, $cursor, $listEnd);
        }

        return new SaslOutcome(
            code: $code,
            additionalData: $additionalData,
        );
    }

    private function encodeBinary(string $value): string
    {
        return chr(self::CONSTRUCTOR_VBIN8) . chr(strlen($value)) . $value;
    }

    /**
     * @return array{0: SaslOutcomeCode, 1: int}
     */
    private function decodeCode(string $bytes, int $cursor, int $listEnd): array
    {
        if ($cursor >= $listEnd) {
            throw SaslException::truncatedOutcome();
        }

        if (ord($bytes[$cursor]) !== self::CONSTRUCTOR_UBYTE) {
            throw SaslException::missingOutcomeCode();
        }

        ++$cursor;

        if ($cursor >= $listEnd) {
            throw SaslException::truncatedOutcome();
        }

        $code = SaslOutcomeCode::tryFrom(ord($bytes[$cursor]));

        if ($code === null) {
            throw SaslException::missingOutcomeCode();
        }

        return [
            $code,
            $cursor + 1,
        ];
    }

    /**
     * @return array{0: ?string, 1: int}
     */
    private function decodeNullableBinary(string $bytes, int $cursor, int $listEnd): array
    {
        if ($cursor >= $listEnd) {
            throw SaslException::truncatedOutcome();
        }

        if (ord($bytes[$cursor]) === self::CONSTRUCTOR_NULL) {
            return [null, $cursor + 1];
        }

        if (ord($bytes[$cursor]) !== self::CONSTRUCTOR_VBIN8) {
            throw SaslException::truncatedOutcome();
        }

        ++$cursor;

        if ($cursor >= $listEnd) {
            throw SaslException::truncatedOutcome();
        }

        $length = ord($bytes[$cursor]);
        ++$cursor;

        if ($cursor + $length > $listEnd) {
            throw SaslException::truncatedOutcome();
        }

        return [
            substr($bytes, $cursor, $length),
            $cursor + $length,
        ];
    }
}
