<?php

declare(strict_types=1);

namespace Sigbits\Amqp\Protocol\Message;

final class MessageCodec
{
    private const string HEADER_DESCRIPTOR = "\x00\x53\x70";
    private const string MESSAGE_ANNOTATIONS_DESCRIPTOR = "\x00\x53\x72";
    private const string PROPERTIES_DESCRIPTOR = "\x00\x53\x73";
    private const string APPLICATION_PROPERTIES_DESCRIPTOR = "\x00\x53\x74";
    private const string DATA_DESCRIPTOR = "\x00\x53\x75";
    private const int HEADER_DESCRIPTOR_LENGTH = 3;
    private const int MESSAGE_ANNOTATIONS_DESCRIPTOR_LENGTH = 3;
    private const int PROPERTIES_DESCRIPTOR_LENGTH = 3;
    private const int APPLICATION_PROPERTIES_DESCRIPTOR_LENGTH = 3;
    private const int DATA_DESCRIPTOR_LENGTH = 3;
    private const int CONSTRUCTOR_BOOL = 0x56;
    private const int CONSTRUCTOR_BOOL_TRUE = 0x41;
    private const int CONSTRUCTOR_BOOL_FALSE = 0x42;
    private const int CONSTRUCTOR_LIST0 = 0x45;
    private const int CONSTRUCTOR_LIST8 = 0xc0;
    private const int CONSTRUCTOR_MAP8 = 0xc1;
    private const int CONSTRUCTOR_NULL = 0x40;
    private const int CONSTRUCTOR_STRING8 = 0xa1;
    private const int CONSTRUCTOR_SYMBOL8 = 0xa3;
    private const int CONSTRUCTOR_UBYTE = 0x50;
    private const int CONSTRUCTOR_UINT = 0x70;
    private const int CONSTRUCTOR_VBIN8 = 0xa0;

    public function encode(Message $message): string
    {
        $sections = '';

        if ($message->header !== null) {
            $sections .= $this->encodeHeader($message->header);
        }

        if ($message->messageAnnotations !== []) {
            $sections .= $this->encodeMessageAnnotations($message->messageAnnotations);
        }

        if ($message->properties !== null) {
            $sections .= $this->encodeProperties($message->properties);
        }

        if ($message->applicationProperties !== []) {
            $sections .= $this->encodeApplicationProperties($message->applicationProperties);
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
        $header = null;

        if (substr($bytes, 0, self::HEADER_DESCRIPTOR_LENGTH) === self::HEADER_DESCRIPTOR) {
            [$header, $cursor] = $this->decodeHeader($bytes, $cursor);
        }

        $messageAnnotations = [];

        if (substr($bytes, $cursor, self::MESSAGE_ANNOTATIONS_DESCRIPTOR_LENGTH) === self::MESSAGE_ANNOTATIONS_DESCRIPTOR) {
            [$messageAnnotations, $cursor] = $this->decodeMessageAnnotations($bytes, $cursor);
        }

        $properties = null;

        if (substr($bytes, $cursor, self::PROPERTIES_DESCRIPTOR_LENGTH) === self::PROPERTIES_DESCRIPTOR) {
            [$properties, $cursor] = $this->decodeProperties($bytes, $cursor);
        }

        $applicationProperties = [];

        if (substr($bytes, $cursor, self::APPLICATION_PROPERTIES_DESCRIPTOR_LENGTH) === self::APPLICATION_PROPERTIES_DESCRIPTOR) {
            [$applicationProperties, $cursor] = $this->decodeApplicationProperties($bytes, $cursor);
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
            header: $header,
            messageAnnotations: $messageAnnotations,
            properties: $properties,
            applicationProperties: $applicationProperties,
        );
    }

    private function encodeHeader(Header $header): string
    {
        $fields = [
            $this->encodeNullableBoolean($header->durable),
            $this->encodeNullableUByte($header->priority),
            $this->encodeNullableUInt($header->ttl),
        ];

        $lastSetField = -1;

        foreach ($fields as $index => $field) {
            if ($field !== chr(self::CONSTRUCTOR_NULL)) {
                $lastSetField = $index;
            }
        }

        if ($lastSetField === -1) {
            return self::HEADER_DESCRIPTOR . chr(self::CONSTRUCTOR_LIST0);
        }

        $encodedFields = implode('', array_slice($fields, 0, $lastSetField + 1));
        $listSize = 1 + strlen($encodedFields);

        return self::HEADER_DESCRIPTOR
            . chr(self::CONSTRUCTOR_LIST8)
            . chr($listSize)
            . chr($lastSetField + 1)
            . $encodedFields;
    }

    private function encodeNullableBoolean(?bool $value): string
    {
        if ($value === null) {
            return chr(self::CONSTRUCTOR_NULL);
        }

        return chr($value ? self::CONSTRUCTOR_BOOL_TRUE : self::CONSTRUCTOR_BOOL_FALSE);
    }

    private function encodeNullableUByte(?int $value): string
    {
        if ($value === null) {
            return chr(self::CONSTRUCTOR_NULL);
        }

        return chr(self::CONSTRUCTOR_UBYTE) . chr($value);
    }

    private function encodeNullableUInt(?int $value): string
    {
        if ($value === null) {
            return chr(self::CONSTRUCTOR_NULL);
        }

        return chr(self::CONSTRUCTOR_UINT) . pack('N', $value);
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
     * @param array<string, string> $properties
     */
    private function encodeApplicationProperties(array $properties): string
    {
        $entries = '';

        foreach ($properties as $name => $value) {
            $entries .= $this->encodeSymbol($name) . $this->encodeString($value);
        }

        $mapSize = 1 + strlen($entries);

        if ($mapSize > 255) {
            throw new \InvalidArgumentException('AMQP application properties must fit in map8 encoding.');
        }

        return self::APPLICATION_PROPERTIES_DESCRIPTOR
            . chr(self::CONSTRUCTOR_MAP8)
            . chr($mapSize)
            . chr(count($properties) * 2)
            . $entries;
    }

    private function encodeString(string $value): string
    {
        return chr(self::CONSTRUCTOR_STRING8) . chr(strlen($value)) . $value;
    }

    private function encodeSymbol(string $value): string
    {
        return chr(self::CONSTRUCTOR_SYMBOL8) . chr(strlen($value)) . $value;
    }

    /**
     * @param array<string, string> $annotations
     */
    private function encodeMessageAnnotations(array $annotations): string
    {
        $entries = '';

        foreach ($annotations as $name => $value) {
            $entries .= $this->encodeSymbol($name) . $this->encodeString($value);
        }

        $mapSize = 1 + strlen($entries);

        if ($mapSize > 255) {
            throw new \InvalidArgumentException('AMQP message annotations must fit in map8 encoding.');
        }

        return self::MESSAGE_ANNOTATIONS_DESCRIPTOR
            . chr(self::CONSTRUCTOR_MAP8)
            . chr($mapSize)
            . chr(count($annotations) * 2)
            . $entries;
    }

    /**
     * @return array{0: Header, 1: int}
     */
    private function decodeHeader(string $bytes, int $cursor): array
    {
        if (strlen($bytes) < $cursor + self::HEADER_DESCRIPTOR_LENGTH + 1) {
            throw MessageException::truncatedHeader();
        }

        if (substr($bytes, $cursor, self::HEADER_DESCRIPTOR_LENGTH) !== self::HEADER_DESCRIPTOR) {
            throw MessageException::malformedHeader();
        }

        $cursor += self::HEADER_DESCRIPTOR_LENGTH;
        $constructor = ord($bytes[$cursor]);
        ++$cursor;

        if ($constructor === self::CONSTRUCTOR_LIST0) {
            return [new Header(), $cursor];
        }

        if ($constructor !== self::CONSTRUCTOR_LIST8) {
            throw MessageException::malformedHeader();
        }

        if (strlen($bytes) < $cursor + 2) {
            throw MessageException::truncatedHeader();
        }

        $listSize = ord($bytes[$cursor]);
        ++$cursor;
        $fieldCount = ord($bytes[$cursor]);
        ++$cursor;
        $listEnd = $cursor + $listSize - 1;

        if (strlen($bytes) < $listEnd) {
            throw MessageException::truncatedHeader();
        }

        $durable = null;
        $priority = null;
        $ttl = null;

        for ($field = 0; $field < $fieldCount; ++$field) {
            if ($cursor >= $listEnd) {
                throw MessageException::truncatedHeader();
            }

            if ($field === 0) {
                [$durable, $cursor] = $this->decodeNullableBoolean($bytes, $cursor, $listEnd);
                continue;
            }

            if ($field === 1) {
                [$priority, $cursor] = $this->decodeNullableUByte($bytes, $cursor, $listEnd);
                continue;
            }

            if ($field === 2) {
                [$ttl, $cursor] = $this->decodeNullableUInt($bytes, $cursor, $listEnd);
                continue;
            }

            $cursor = $this->skipHeaderNull($bytes, $cursor, $listEnd);
        }

        return [
            new Header(
                durable: $durable,
                priority: $priority,
                ttl: $ttl,
            ),
            $cursor,
        ];
    }

    /**
     * @return array{0: ?bool, 1: int}
     */
    private function decodeNullableBoolean(string $bytes, int $cursor, int $listEnd): array
    {
        $constructor = ord($bytes[$cursor]);

        if ($constructor === self::CONSTRUCTOR_NULL) {
            return [null, $cursor + 1];
        }

        if ($constructor === self::CONSTRUCTOR_BOOL_TRUE) {
            return [true, $cursor + 1];
        }

        if ($constructor === self::CONSTRUCTOR_BOOL_FALSE) {
            return [false, $cursor + 1];
        }

        if ($constructor !== self::CONSTRUCTOR_BOOL) {
            throw MessageException::malformedHeader();
        }

        ++$cursor;

        if ($cursor >= $listEnd) {
            throw MessageException::truncatedHeader();
        }

        return [ord($bytes[$cursor]) !== 0, $cursor + 1];
    }

    /**
     * @return array{0: ?int, 1: int}
     */
    private function decodeNullableUByte(string $bytes, int $cursor, int $listEnd): array
    {
        if (ord($bytes[$cursor]) === self::CONSTRUCTOR_NULL) {
            return [null, $cursor + 1];
        }

        if (ord($bytes[$cursor]) !== self::CONSTRUCTOR_UBYTE) {
            throw MessageException::malformedHeader();
        }

        ++$cursor;

        if ($cursor >= $listEnd) {
            throw MessageException::truncatedHeader();
        }

        return [ord($bytes[$cursor]), $cursor + 1];
    }

    /**
     * @return array{0: ?int, 1: int}
     */
    private function decodeNullableUInt(string $bytes, int $cursor, int $listEnd): array
    {
        if (ord($bytes[$cursor]) === self::CONSTRUCTOR_NULL) {
            return [null, $cursor + 1];
        }

        if (ord($bytes[$cursor]) !== self::CONSTRUCTOR_UINT) {
            throw MessageException::malformedHeader();
        }

        ++$cursor;

        if ($cursor + 4 > $listEnd) {
            throw MessageException::truncatedHeader();
        }

        return [
            (ord($bytes[$cursor]) << 24)
            | (ord($bytes[$cursor + 1]) << 16)
            | (ord($bytes[$cursor + 2]) << 8)
            | ord($bytes[$cursor + 3]),
            $cursor + 4,
        ];
    }

    private function skipHeaderNull(string $bytes, int $cursor, int $listEnd): int
    {
        if ($cursor >= $listEnd) {
            throw MessageException::truncatedHeader();
        }

        if (ord($bytes[$cursor]) !== self::CONSTRUCTOR_NULL) {
            throw MessageException::malformedHeader();
        }

        return $cursor + 1;
    }

    /**
     * @return array{0: array<string, string>, 1: int}
     */
    private function decodeMessageAnnotations(string $bytes, int $cursor): array
    {
        if (strlen($bytes) < $cursor + self::MESSAGE_ANNOTATIONS_DESCRIPTOR_LENGTH + 1) {
            throw MessageException::truncatedMessageAnnotations();
        }

        if (substr($bytes, $cursor, self::MESSAGE_ANNOTATIONS_DESCRIPTOR_LENGTH) !== self::MESSAGE_ANNOTATIONS_DESCRIPTOR) {
            throw MessageException::malformedMessageAnnotations();
        }

        $cursor += self::MESSAGE_ANNOTATIONS_DESCRIPTOR_LENGTH;

        if (ord($bytes[$cursor]) !== self::CONSTRUCTOR_MAP8) {
            throw MessageException::malformedMessageAnnotations();
        }

        ++$cursor;

        if (strlen($bytes) < $cursor + 2) {
            throw MessageException::truncatedMessageAnnotations();
        }

        $mapSize = ord($bytes[$cursor]);
        ++$cursor;
        $mapCount = ord($bytes[$cursor]);
        ++$cursor;
        $mapEnd = $cursor + $mapSize - 1;

        if ($mapCount % 2 !== 0) {
            throw MessageException::malformedMessageAnnotations();
        }

        if (strlen($bytes) < $mapEnd) {
            throw MessageException::truncatedMessageAnnotations();
        }

        $annotations = [];

        for ($i = 0; $i < $mapCount; $i += 2) {
            [$name, $cursor] = $this->decodeAnnotationSymbol($bytes, $cursor, $mapEnd);
            [$value, $cursor] = $this->decodeAnnotationString($bytes, $cursor, $mapEnd);
            $annotations[$name] = $value;
        }

        return [$annotations, $cursor];
    }

    /**
     * @return array{0: string, 1: int}
     */
    private function decodeAnnotationSymbol(string $bytes, int $cursor, int $mapEnd): array
    {
        if ($cursor >= $mapEnd) {
            throw MessageException::truncatedMessageAnnotations();
        }

        if (ord($bytes[$cursor]) !== self::CONSTRUCTOR_SYMBOL8) {
            throw MessageException::malformedMessageAnnotations();
        }

        return $this->decodeAnnotationString8($bytes, $cursor + 1, $mapEnd);
    }

    /**
     * @return array{0: string, 1: int}
     */
    private function decodeAnnotationString(string $bytes, int $cursor, int $mapEnd): array
    {
        if ($cursor >= $mapEnd) {
            throw MessageException::truncatedMessageAnnotations();
        }

        if (ord($bytes[$cursor]) !== self::CONSTRUCTOR_STRING8) {
            throw MessageException::malformedMessageAnnotations();
        }

        return $this->decodeAnnotationString8($bytes, $cursor + 1, $mapEnd);
    }

    /**
     * @return array{0: string, 1: int}
     */
    private function decodeAnnotationString8(string $bytes, int $cursor, int $mapEnd): array
    {
        if ($cursor >= $mapEnd) {
            throw MessageException::truncatedMessageAnnotations();
        }

        $length = ord($bytes[$cursor]);
        ++$cursor;

        if ($cursor + $length > $mapEnd) {
            throw MessageException::truncatedMessageAnnotations();
        }

        return [
            substr($bytes, $cursor, $length),
            $cursor + $length,
        ];
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

    /**
     * @return array{0: array<string, string>, 1: int}
     */
    private function decodeApplicationProperties(string $bytes, int $cursor): array
    {
        if (strlen($bytes) < $cursor + self::APPLICATION_PROPERTIES_DESCRIPTOR_LENGTH + 1) {
            throw MessageException::truncatedApplicationProperties();
        }

        if (substr($bytes, $cursor, self::APPLICATION_PROPERTIES_DESCRIPTOR_LENGTH) !== self::APPLICATION_PROPERTIES_DESCRIPTOR) {
            throw MessageException::malformedApplicationProperties();
        }

        $cursor += self::APPLICATION_PROPERTIES_DESCRIPTOR_LENGTH;

        if (ord($bytes[$cursor]) !== self::CONSTRUCTOR_MAP8) {
            throw MessageException::malformedApplicationProperties();
        }

        ++$cursor;

        if (strlen($bytes) < $cursor + 2) {
            throw MessageException::truncatedApplicationProperties();
        }

        $mapSize = ord($bytes[$cursor]);
        ++$cursor;
        $mapCount = ord($bytes[$cursor]);
        ++$cursor;
        $mapEnd = $cursor + $mapSize - 1;

        if ($mapCount % 2 !== 0) {
            throw MessageException::malformedApplicationProperties();
        }

        if (strlen($bytes) < $mapEnd) {
            throw MessageException::truncatedApplicationProperties();
        }

        $properties = [];

        for ($i = 0; $i < $mapCount; $i += 2) {
            [$name, $cursor] = $this->decodeSymbol($bytes, $cursor, $mapEnd);
            [$value, $cursor] = $this->decodeString($bytes, $cursor, $mapEnd);
            $properties[$name] = $value;
        }

        return [$properties, $cursor];
    }

    /**
     * @return array{0: string, 1: int}
     */
    private function decodeSymbol(string $bytes, int $cursor, int $mapEnd): array
    {
        if ($cursor >= $mapEnd) {
            throw MessageException::truncatedApplicationProperties();
        }

        if (ord($bytes[$cursor]) !== self::CONSTRUCTOR_SYMBOL8) {
            throw MessageException::malformedApplicationProperties();
        }

        return $this->decodeMapString8($bytes, $cursor + 1, $mapEnd);
    }

    /**
     * @return array{0: string, 1: int}
     */
    private function decodeString(string $bytes, int $cursor, int $mapEnd): array
    {
        if ($cursor >= $mapEnd) {
            throw MessageException::truncatedApplicationProperties();
        }

        if (ord($bytes[$cursor]) !== self::CONSTRUCTOR_STRING8) {
            throw MessageException::malformedApplicationProperties();
        }

        return $this->decodeMapString8($bytes, $cursor + 1, $mapEnd);
    }

    /**
     * @return array{0: string, 1: int}
     */
    private function decodeMapString8(string $bytes, int $cursor, int $mapEnd): array
    {
        if ($cursor >= $mapEnd) {
            throw MessageException::truncatedApplicationProperties();
        }

        $length = ord($bytes[$cursor]);
        ++$cursor;

        if ($cursor + $length > $mapEnd) {
            throw MessageException::truncatedApplicationProperties();
        }

        return [
            substr($bytes, $cursor, $length),
            $cursor + $length,
        ];
    }
}
