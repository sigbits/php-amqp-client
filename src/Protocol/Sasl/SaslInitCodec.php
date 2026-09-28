<?php

declare(strict_types=1);

namespace Sigbits\Amqp\Protocol\Sasl;

final class SaslInitCodec
{
    private const string DESCRIPTOR = "\x00\x53\x41";
    private const int DESCRIPTOR_LENGTH = 3;
    private const int CONSTRUCTOR_LIST0 = 0x45;
    private const int CONSTRUCTOR_LIST8 = 0xc0;
    private const int CONSTRUCTOR_SYMBOL8 = 0xa3;
    private const int CONSTRUCTOR_VBIN8 = 0xa0;

    public function encode(SaslInit $init): string
    {
        $fields = $this->encodeSymbol($init->mechanism);
        $fieldCount = 1;

        if ($init->initialResponse !== null) {
            $fields .= $this->encodeBinary($init->initialResponse);
            $fieldCount = 2;
        }

        $listSize = 1 + strlen($fields);

        return self::DESCRIPTOR
            . chr(self::CONSTRUCTOR_LIST8)
            . chr($listSize)
            . chr($fieldCount)
            . $fields;
    }

    public function decode(string $bytes): SaslInit
    {
        if (strlen($bytes) < self::DESCRIPTOR_LENGTH + 1) {
            throw SaslException::truncatedInit();
        }

        if (substr($bytes, 0, self::DESCRIPTOR_LENGTH) !== self::DESCRIPTOR) {
            throw SaslException::expectedInitDescriptor();
        }

        $cursor = self::DESCRIPTOR_LENGTH;
        $constructor = ord($bytes[$cursor]);
        ++$cursor;

        if ($constructor === self::CONSTRUCTOR_LIST0) {
            throw SaslException::missingInitMechanism();
        }

        if ($constructor !== self::CONSTRUCTOR_LIST8) {
            throw SaslException::truncatedInit();
        }

        if (strlen($bytes) < $cursor + 2) {
            throw SaslException::truncatedInit();
        }

        $listSize = ord($bytes[$cursor]);
        ++$cursor;
        $fieldCount = ord($bytes[$cursor]);
        ++$cursor;

        if ($fieldCount < 1) {
            throw SaslException::missingInitMechanism();
        }

        $listEnd = $cursor + $listSize - 1;

        if (strlen($bytes) < $listEnd) {
            throw SaslException::truncatedInit();
        }

        [$mechanism, $cursor] = $this->decodeSymbol($bytes, $cursor, $listEnd);
        $initialResponse = null;

        if ($fieldCount >= 2) {
            [$initialResponse] = $this->decodeBinary($bytes, $cursor, $listEnd);
        }

        return new SaslInit(
            mechanism: $mechanism,
            initialResponse: $initialResponse,
        );
    }

    private function encodeSymbol(string $value): string
    {
        return chr(self::CONSTRUCTOR_SYMBOL8) . chr(strlen($value)) . $value;
    }

    private function encodeBinary(string $value): string
    {
        return chr(self::CONSTRUCTOR_VBIN8) . chr(strlen($value)) . $value;
    }

    /**
     * @return array{0: string, 1: int}
     */
    private function decodeSymbol(string $bytes, int $cursor, int $listEnd): array
    {
        if ($cursor >= $listEnd) {
            throw SaslException::truncatedInit();
        }

        if (ord($bytes[$cursor]) !== self::CONSTRUCTOR_SYMBOL8) {
            throw SaslException::missingInitMechanism();
        }

        ++$cursor;

        if ($cursor >= $listEnd) {
            throw SaslException::truncatedInit();
        }

        $length = ord($bytes[$cursor]);
        ++$cursor;

        if ($cursor + $length > $listEnd) {
            throw SaslException::truncatedInit();
        }

        return [
            substr($bytes, $cursor, $length),
            $cursor + $length,
        ];
    }

    /**
     * @return array{0: string, 1: int}
     */
    private function decodeBinary(string $bytes, int $cursor, int $listEnd): array
    {
        if ($cursor >= $listEnd) {
            throw SaslException::truncatedInit();
        }

        if (ord($bytes[$cursor]) !== self::CONSTRUCTOR_VBIN8) {
            throw SaslException::truncatedInit();
        }

        ++$cursor;

        if ($cursor >= $listEnd) {
            throw SaslException::truncatedInit();
        }

        $length = ord($bytes[$cursor]);
        ++$cursor;

        if ($cursor + $length > $listEnd) {
            throw SaslException::truncatedInit();
        }

        return [
            substr($bytes, $cursor, $length),
            $cursor + $length,
        ];
    }
}
