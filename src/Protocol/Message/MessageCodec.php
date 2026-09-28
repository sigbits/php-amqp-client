<?php

declare(strict_types=1);

namespace Sigbits\Amqp\Protocol\Message;

final class MessageCodec
{
    private const string PROPERTIES_DESCRIPTOR = "\x00\x53\x73";
    private const string DATA_DESCRIPTOR = "\x00\x53\x75";
    private const int PROPERTIES_DESCRIPTOR_LENGTH = 3;
    private const int DATA_DESCRIPTOR_LENGTH = 3;
    private const int CONSTRUCTOR_LIST0 = 0x45;
    private const int CONSTRUCTOR_LIST8 = 0xc0;
    private const int CONSTRUCTOR_NULL = 0x40;
    private const int CONSTRUCTOR_STRING8 = 0xa1;
    private const int CONSTRUCTOR_SYMBOL8 = 0xa3;
    private const int CONSTRUCTOR_VBIN8 = 0xa0;

    public function encode(Message $message): string
    {
        $sections = '';

        if ($message->properties !== null) {
            $sections .= $this->encodeProperties($message->properties);
        }

        return $sections
            . self::DATA_DESCRIPTOR
            . chr(self::CONSTRUCTOR_VBIN8)
            . chr(strlen($message->body))
            . $message->body;
    }

    public function decode(string $bytes): Message
    {
        $cursor = 0;
        $properties = null;

        if (substr($bytes, 0, self::PROPERTIES_DESCRIPTOR_LENGTH) === self::PROPERTIES_DESCRIPTOR) {
            [$properties, $cursor] = $this->decodeProperties($bytes, $cursor);
        }

        if (strlen($bytes) < $cursor + self::DATA_DESCRIPTOR_LENGTH + 1) {
            throw MessageException::truncatedDataBody();
        }

        if (substr($bytes, $cursor, self::DATA_DESCRIPTOR_LENGTH) !== self::DATA_DESCRIPTOR) {
            throw MessageException::expectedDataBodyDescriptor();
        }

        $cursor += self::DATA_DESCRIPTOR_LENGTH;

        if (ord($bytes[$cursor]) !== self::CONSTRUCTOR_VBIN8) {
            throw MessageException::unsupportedDataBodyEncoding();
        }

        ++$cursor;

        if (strlen($bytes) <= $cursor) {
            throw MessageException::truncatedDataBody();
        }

        $length = ord($bytes[$cursor]);
        ++$cursor;

        if (strlen($bytes) < $cursor + $length) {
            throw MessageException::truncatedDataBody();
        }

        return new Message(
            body: substr($bytes, $cursor, $length),
            properties: $properties,
        );
    }

    private function encodeProperties(Properties $properties): string
    {
        $fields = [
            $this->encodeNullableString($properties->messageId),
            chr(self::CONSTRUCTOR_NULL),
            chr(self::CONSTRUCTOR_NULL),
            $this->encodeNullableString($properties->subject),
            chr(self::CONSTRUCTOR_NULL),
            $this->encodeNullableString($properties->correlationId),
            $this->encodeNullableSymbol($properties->contentType),
        ];

        $lastSetField = -1;

        foreach ($fields as $index => $field) {
            if ($field !== chr(self::CONSTRUCTOR_NULL)) {
                $lastSetField = $index;
            }
        }

        if ($lastSetField === -1) {
            return self::PROPERTIES_DESCRIPTOR . chr(self::CONSTRUCTOR_LIST0);
        }

        $encodedFields = implode('', array_slice($fields, 0, $lastSetField + 1));
        $listSize = 1 + strlen($encodedFields);

        return self::PROPERTIES_DESCRIPTOR
            . chr(self::CONSTRUCTOR_LIST8)
            . chr($listSize)
            . chr($lastSetField + 1)
            . $encodedFields;
    }

    private function encodeNullableString(?string $value): string
    {
        if ($value === null) {
            return chr(self::CONSTRUCTOR_NULL);
        }

        return chr(self::CONSTRUCTOR_STRING8) . chr(strlen($value)) . $value;
    }

    private function encodeNullableSymbol(?string $value): string
    {
        if ($value === null) {
            return chr(self::CONSTRUCTOR_NULL);
        }

        return chr(self::CONSTRUCTOR_SYMBOL8) . chr(strlen($value)) . $value;
    }

    /**
     * @return array{0: Properties, 1: int}
     */
    private function decodeProperties(string $bytes, int $cursor): array
    {
        if (strlen($bytes) < $cursor + self::PROPERTIES_DESCRIPTOR_LENGTH + 1) {
            throw MessageException::truncatedProperties();
        }

        if (substr($bytes, $cursor, self::PROPERTIES_DESCRIPTOR_LENGTH) !== self::PROPERTIES_DESCRIPTOR) {
            throw MessageException::malformedProperties();
        }

        $cursor += self::PROPERTIES_DESCRIPTOR_LENGTH;
        $constructor = ord($bytes[$cursor]);
        ++$cursor;

        if ($constructor === self::CONSTRUCTOR_LIST0) {
            return [new Properties(), $cursor];
        }

        if ($constructor !== self::CONSTRUCTOR_LIST8) {
            throw MessageException::malformedProperties();
        }

        if (strlen($bytes) < $cursor + 2) {
            throw MessageException::truncatedProperties();
        }

        $listSize = ord($bytes[$cursor]);
        ++$cursor;
        $fieldCount = ord($bytes[$cursor]);
        ++$cursor;
        $listEnd = $cursor + $listSize - 1;

        if (strlen($bytes) < $listEnd) {
            throw MessageException::truncatedProperties();
        }

        $messageId = null;
        $correlationId = null;
        $contentType = null;
        $subject = null;

        for ($field = 0; $field < $fieldCount; ++$field) {
            if ($cursor >= $listEnd) {
                throw MessageException::truncatedProperties();
            }

            if ($field === 0) {
                [$messageId, $cursor] = $this->decodeNullableString($bytes, $cursor, $listEnd);
                continue;
            }

            if ($field === 3) {
                [$subject, $cursor] = $this->decodeNullableString($bytes, $cursor, $listEnd);
                continue;
            }

            if ($field === 5) {
                [$correlationId, $cursor] = $this->decodeNullableString($bytes, $cursor, $listEnd);
                continue;
            }

            if ($field === 6) {
                [$contentType, $cursor] = $this->decodeNullableSymbol($bytes, $cursor, $listEnd);
                continue;
            }

            $cursor = $this->skipNull($bytes, $cursor, $listEnd);
        }

        return [
            new Properties(
                messageId: $messageId,
                correlationId: $correlationId,
                contentType: $contentType,
                subject: $subject,
            ),
            $cursor,
        ];
    }

    /**
     * @return array{0: ?string, 1: int}
     */
    private function decodeNullableString(string $bytes, int $cursor, int $listEnd): array
    {
        if (ord($bytes[$cursor]) === self::CONSTRUCTOR_NULL) {
            return [null, $cursor + 1];
        }

        if (ord($bytes[$cursor]) !== self::CONSTRUCTOR_STRING8) {
            throw MessageException::malformedProperties();
        }

        return $this->decodeString8($bytes, $cursor + 1, $listEnd);
    }

    /**
     * @return array{0: ?string, 1: int}
     */
    private function decodeNullableSymbol(string $bytes, int $cursor, int $listEnd): array
    {
        if (ord($bytes[$cursor]) === self::CONSTRUCTOR_NULL) {
            return [null, $cursor + 1];
        }

        if (ord($bytes[$cursor]) !== self::CONSTRUCTOR_SYMBOL8) {
            throw MessageException::malformedProperties();
        }

        return $this->decodeString8($bytes, $cursor + 1, $listEnd);
    }

    /**
     * @return array{0: string, 1: int}
     */
    private function decodeString8(string $bytes, int $cursor, int $listEnd): array
    {
        if ($cursor >= $listEnd) {
            throw MessageException::truncatedProperties();
        }

        $length = ord($bytes[$cursor]);
        ++$cursor;

        if ($cursor + $length > $listEnd) {
            throw MessageException::truncatedProperties();
        }

        return [
            substr($bytes, $cursor, $length),
            $cursor + $length,
        ];
    }

    private function skipNull(string $bytes, int $cursor, int $listEnd): int
    {
        if ($cursor >= $listEnd) {
            throw MessageException::truncatedProperties();
        }

        if (ord($bytes[$cursor]) !== self::CONSTRUCTOR_NULL) {
            throw MessageException::malformedProperties();
        }

        return $cursor + 1;
    }
}
