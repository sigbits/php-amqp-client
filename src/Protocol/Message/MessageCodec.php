<?php

declare(strict_types=1);

namespace Sigbits\Amqp\Protocol\Message;

use Sigbits\Amqp\Protocol\Codec\DecodeException;
use Sigbits\Amqp\Protocol\Codec\Decoder;
use Sigbits\Amqp\Protocol\Codec\Encoder;
use Sigbits\Amqp\Protocol\Type\AmqpMap;
use Sigbits\Amqp\Protocol\Type\Symbol;

final class MessageCodec
{
    private const string HEADER_DESCRIPTOR = "\x00\x53\x70";
    private const string DELIVERY_ANNOTATIONS_DESCRIPTOR = "\x00\x53\x71";
    private const string MESSAGE_ANNOTATIONS_DESCRIPTOR = "\x00\x53\x72";
    private const string PROPERTIES_DESCRIPTOR = "\x00\x53\x73";
    private const string APPLICATION_PROPERTIES_DESCRIPTOR = "\x00\x53\x74";
    private const string DATA_DESCRIPTOR = "\x00\x53\x75";
    private const string AMQP_SEQUENCE_DESCRIPTOR = "\x00\x53\x76";
    private const string AMQP_VALUE_DESCRIPTOR = "\x00\x53\x77";
    private const string FOOTER_DESCRIPTOR = "\x00\x53\x78";
    private const int HEADER_DESCRIPTOR_LENGTH = 3;
    private const int DELIVERY_ANNOTATIONS_DESCRIPTOR_LENGTH = 3;
    private const int MESSAGE_ANNOTATIONS_DESCRIPTOR_LENGTH = 3;
    private const int PROPERTIES_DESCRIPTOR_LENGTH = 3;
    private const int APPLICATION_PROPERTIES_DESCRIPTOR_LENGTH = 3;
    private const int DATA_DESCRIPTOR_LENGTH = 3;
    private const int AMQP_SEQUENCE_DESCRIPTOR_LENGTH = 3;
    private const int AMQP_VALUE_DESCRIPTOR_LENGTH = 3;
    private const int FOOTER_DESCRIPTOR_LENGTH = 3;
    private const int CONSTRUCTOR_DESCRIBED = 0x00;
    private const int CONSTRUCTOR_BOOL = 0x56;
    private const int CONSTRUCTOR_BOOL_TRUE = 0x41;
    private const int CONSTRUCTOR_BOOL_FALSE = 0x42;
    private const int CONSTRUCTOR_UINT0 = 0x43;
    private const int CONSTRUCTOR_ULONG0 = 0x44;
    private const int CONSTRUCTOR_LIST0 = 0x45;
    private const int CONSTRUCTOR_LIST8 = 0xc0;
    private const int CONSTRUCTOR_LIST32 = 0xd0;
    private const int CONSTRUCTOR_MAP8 = 0xc1;
    private const int CONSTRUCTOR_MAP32 = 0xd1;
    private const int CONSTRUCTOR_ARRAY8 = 0xe0;
    private const int CONSTRUCTOR_ARRAY32 = 0xf0;
    private const int CONSTRUCTOR_NULL = 0x40;
    private const int CONSTRUCTOR_SMALLUINT = 0x52;
    private const int CONSTRUCTOR_SMALLULONG = 0x53;
    private const int CONSTRUCTOR_SMALLINT = 0x54;
    private const int CONSTRUCTOR_SMALLLONG = 0x55;
    private const int CONSTRUCTOR_STRING8 = 0xa1;
    private const int CONSTRUCTOR_STRING32 = 0xb1;
    private const int CONSTRUCTOR_SYMBOL8 = 0xa3;
    private const int CONSTRUCTOR_SYMBOL32 = 0xb3;
    private const int CONSTRUCTOR_USHORT = 0x60;
    private const int CONSTRUCTOR_UBYTE = 0x50;
    private const int CONSTRUCTOR_UINT = 0x70;
    private const int CONSTRUCTOR_ULONG = 0x80;
    private const int CONSTRUCTOR_FLOAT = 0x72;
    private const int CONSTRUCTOR_CHAR = 0x73;
    private const int CONSTRUCTOR_DECIMAL32 = 0x74;
    private const int CONSTRUCTOR_DOUBLE = 0x82;
    private const int CONSTRUCTOR_TIMESTAMP = 0x83;
    private const int CONSTRUCTOR_DECIMAL64 = 0x84;
    private const int CONSTRUCTOR_DECIMAL128 = 0x94;
    private const int CONSTRUCTOR_UUID = 0x98;
    private const int CONSTRUCTOR_VBIN8 = 0xa0;
    private const int CONSTRUCTOR_VBIN32 = 0xb0;

    public function encode(Message $message): string
    {
        $sections = '';

        if ($message->header !== null) {
            $sections .= $this->encodeHeader($message->header);
        }

        if ($message->deliveryAnnotations !== []) {
            $sections .= $this->encodeDeliveryAnnotations($message->deliveryAnnotations);
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

        $sections .= match ($message->bodySection) {
            MessageBodySection::Data => $this->encodeDataBody($message->body),
            MessageBodySection::AmqpSequence => $this->encodeAmqpSequenceBody($message->bodySequence),
            MessageBodySection::AmqpValue => $this->encodeAmqpValueBody($message),
        };

        if ($message->footer !== []) {
            $sections .= $this->encodeFooter($message->footer);
        }

        return $sections;
    }

    public function decode(string $bytes): Message
    {
        $cursor = 0;
        $header = null;

        if (substr($bytes, 0, self::HEADER_DESCRIPTOR_LENGTH) === self::HEADER_DESCRIPTOR) {
            [$header, $cursor] = $this->decodeHeader($bytes, $cursor);
        }

        $deliveryAnnotations = [];

        if (substr($bytes, $cursor, self::DELIVERY_ANNOTATIONS_DESCRIPTOR_LENGTH) === self::DELIVERY_ANNOTATIONS_DESCRIPTOR) {
            [$deliveryAnnotations, $cursor] = $this->decodeDeliveryAnnotations($bytes, $cursor);
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

        $bodySection = MessageBodySection::Data;
        $bodyDescriptor = substr($bytes, $cursor, self::DATA_DESCRIPTOR_LENGTH);

        if (
            $bodyDescriptor !== self::DATA_DESCRIPTOR
            && $bodyDescriptor !== self::AMQP_SEQUENCE_DESCRIPTOR
            && $bodyDescriptor !== self::AMQP_VALUE_DESCRIPTOR
        ) {
            throw MessageException::expectedDataBodyDescriptor();
        }

        if ($bodyDescriptor === self::AMQP_SEQUENCE_DESCRIPTOR) {
            $bodySection = MessageBodySection::AmqpSequence;
        }

        if ($bodyDescriptor === self::AMQP_VALUE_DESCRIPTOR) {
            $bodySection = MessageBodySection::AmqpValue;
        }

        $cursor += match ($bodySection) {
            MessageBodySection::Data => self::DATA_DESCRIPTOR_LENGTH,
            MessageBodySection::AmqpSequence => self::AMQP_SEQUENCE_DESCRIPTOR_LENGTH,
            MessageBodySection::AmqpValue => self::AMQP_VALUE_DESCRIPTOR_LENGTH,
        };

        $bodyConstructor = ord($bytes[$cursor]);

        if (
            $bodySection === MessageBodySection::Data
            && $bodyConstructor !== self::CONSTRUCTOR_VBIN8
            && $bodyConstructor !== self::CONSTRUCTOR_VBIN32
        ) {
            throw MessageException::unsupportedDataBodyEncoding();
        }

        if ($bodySection === MessageBodySection::AmqpSequence && $bodyConstructor !== self::CONSTRUCTOR_LIST8) {
            throw MessageException::unsupportedAmqpSequenceEncoding();
        }

        $bodyValue = new UnsetMessageBodyValue();

        if ($bodySection === MessageBodySection::AmqpSequence) {
            [$bodySequence, $cursor] = $this->decodeAmqpSequenceBody($bytes, $cursor);
            $body = '';
        } elseif ($bodySection === MessageBodySection::AmqpValue) {
            [$body, $bodyValue, $cursor] = $this->decodeAmqpValueBody($bytes, $cursor);
            $bodySequence = [];
        } else {
            if ($bodyConstructor === self::CONSTRUCTOR_VBIN32) {
                ++$cursor;

                if (strlen($bytes) < $cursor + 4) {
                    throw MessageException::truncatedDataBody();
                }

                $length = (ord($bytes[$cursor]) << 24)
                    | (ord($bytes[$cursor + 1]) << 16)
                    | (ord($bytes[$cursor + 2]) << 8)
                    | ord($bytes[$cursor + 3]);
                $cursor += 4;
            } else {
                ++$cursor;

                if (strlen($bytes) <= $cursor) {
                    throw MessageException::truncatedDataBody();
                }

                $length = ord($bytes[$cursor]);
                ++$cursor;
            }

            if (strlen($bytes) < $cursor + $length) {
                throw MessageException::truncatedDataBody();
            }

            $body = substr($bytes, $cursor, $length);
            $cursor += $length;
            $bodySequence = [];
        }

        $footer = [];

        if (substr($bytes, $cursor, self::FOOTER_DESCRIPTOR_LENGTH) === self::FOOTER_DESCRIPTOR) {
            [$footer] = $this->decodeFooter($bytes, $cursor);
        }

        return new Message(
            body: $body,
            bodySection: $bodySection,
            bodySequence: $bodySequence,
            header: $header,
            deliveryAnnotations: $deliveryAnnotations,
            messageAnnotations: $messageAnnotations,
            properties: $properties,
            applicationProperties: $applicationProperties,
            footer: $footer,
            bodyValue: $bodyValue,
        );
    }

    private function encodeDataBody(string $body): string
    {
        if (strlen($body) <= 255) {
            return self::DATA_DESCRIPTOR
                . chr(self::CONSTRUCTOR_VBIN8)
                . chr(strlen($body))
                . $body;
        }

        return self::DATA_DESCRIPTOR
            . chr(self::CONSTRUCTOR_VBIN32)
            . pack('N', strlen($body))
            . $body;
    }

    private function encodeAmqpValueBody(Message $message): string
    {
        $value = $message->hasBodyValue() ? $message->bodyValue : $message->body;

        return self::AMQP_VALUE_DESCRIPTOR . (new Encoder())->encode($value);
    }

    private function encodeHeader(Header $header): string
    {
        $fields = [
            $this->encodeNullableBoolean($header->durable),
            $this->encodeNullableUByte($header->priority),
            $this->encodeNullableUInt($header->ttl),
            $this->encodeNullableBoolean($header->firstAcquirer),
            $this->encodeNullableUInt($header->deliveryCount),
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
     * @param array<string, mixed> $properties
     */
    private function encodeApplicationProperties(array $properties): string
    {
        return self::APPLICATION_PROPERTIES_DESCRIPTOR . $this->encodeMessageMap($properties);
    }

    private function encodeString(string $value): string
    {
        return chr(self::CONSTRUCTOR_STRING8) . chr(strlen($value)) . $value;
    }

    /**
     * @param list<string> $items
     */
    private function encodeAmqpSequenceBody(array $items): string
    {
        $encodedItems = '';

        foreach ($items as $item) {
            $encodedItems .= $this->encodeString($item);
        }

        $listSize = 1 + strlen($encodedItems);

        if ($listSize > 255) {
            throw new \InvalidArgumentException('AMQP sequence body must fit in list8 encoding.');
        }

        return self::AMQP_SEQUENCE_DESCRIPTOR
            . chr(self::CONSTRUCTOR_LIST8)
            . chr($listSize)
            . chr(count($items))
            . $encodedItems;
    }

    /**
     * @param array<string, mixed> $footer
     */
    private function encodeFooter(array $footer): string
    {
        return self::FOOTER_DESCRIPTOR . $this->encodeMessageMap($footer);
    }

    /**
     * @param array<string, mixed> $annotations
     */
    private function encodeDeliveryAnnotations(array $annotations): string
    {
        return self::DELIVERY_ANNOTATIONS_DESCRIPTOR . $this->encodeMessageMap($annotations);
    }

    /**
     * @param array<string, mixed> $annotations
     */
    private function encodeMessageAnnotations(array $annotations): string
    {
        return self::MESSAGE_ANNOTATIONS_DESCRIPTOR . $this->encodeMessageMap($annotations);
    }

    /**
     * @param array<string, mixed> $values
     */
    private function encodeMessageMap(array $values): string
    {
        $pairs = [];

        foreach ($values as $name => $value) {
            if ($name === '') {
                throw new \InvalidArgumentException('AMQP message map name must be a non-empty string.');
            }

            $pairs[] = [new Symbol($name), $value];
        }

        return (new Encoder())->encode(new AmqpMap($pairs));
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
        $firstAcquirer = null;
        $deliveryCount = null;

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

            if ($field === 3) {
                [$firstAcquirer, $cursor] = $this->decodeNullableBoolean($bytes, $cursor, $listEnd);
                continue;
            }

            if ($field === 4) {
                [$deliveryCount, $cursor] = $this->decodeNullableUInt($bytes, $cursor, $listEnd);
                continue;
            }

            $cursor = $this->skipHeaderNull($bytes, $cursor, $listEnd);
        }

        return [
            new Header(
                durable: $durable,
                priority: $priority,
                ttl: $ttl,
                firstAcquirer: $firstAcquirer,
                deliveryCount: $deliveryCount,
            ),
            $cursor,
        ];
    }

    /**
     * @return array{0: list<string>, 1: int}
     */
    private function decodeAmqpSequenceBody(string $bytes, int $cursor): array
    {
        if (ord($bytes[$cursor]) !== self::CONSTRUCTOR_LIST8) {
            throw MessageException::unsupportedAmqpSequenceEncoding();
        }

        ++$cursor;

        if (strlen($bytes) < $cursor + 2) {
            throw MessageException::truncatedDataBody();
        }

        $listSize = ord($bytes[$cursor]);
        ++$cursor;
        $fieldCount = ord($bytes[$cursor]);
        ++$cursor;
        $listEnd = $cursor + $listSize - 1;

        if (strlen($bytes) < $listEnd) {
            throw MessageException::truncatedDataBody();
        }

        $items = [];

        for ($i = 0; $i < $fieldCount; ++$i) {
            if ($cursor >= $listEnd) {
                throw MessageException::truncatedDataBody();
            }

            if (ord($bytes[$cursor]) !== self::CONSTRUCTOR_STRING8) {
                throw MessageException::unsupportedAmqpSequenceEncoding();
            }

            [$items[], $cursor] = $this->decodeSequenceString8($bytes, $cursor + 1, $listEnd);
        }

        return [$items, $cursor];
    }

    /**
     * @return array{0: string, 1: mixed, 2: int}
     */
    private function decodeAmqpValueBody(string $bytes, int $cursor): array
    {
        try {
            [$value, $consumed] = (new Decoder())->decodeWithConsumed(substr($bytes, $cursor));
        } catch (DecodeException $exception) {
            if (str_starts_with($exception->getMessage(), 'Truncated AMQP')) {
                throw MessageException::truncatedAmqpValueBody();
            }

            throw MessageException::malformedAmqpValueBody();
        }

        if (is_string($value)) {
            return [$value, new UnsetMessageBodyValue(), $cursor + $consumed];
        }

        return ['', $value, $cursor + $consumed];
    }

    /**
     * @return array{0: string, 1: int}
     */
    private function decodeSequenceString8(string $bytes, int $cursor, int $listEnd): array
    {
        if ($cursor >= $listEnd) {
            throw MessageException::truncatedDataBody();
        }

        $length = ord($bytes[$cursor]);
        ++$cursor;

        if ($cursor + $length > $listEnd) {
            throw MessageException::truncatedDataBody();
        }

        return [
            substr($bytes, $cursor, $length),
            $cursor + $length,
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

        if (ord($bytes[$cursor]) === self::CONSTRUCTOR_UINT0) {
            return [0, $cursor + 1];
        }

        if (ord($bytes[$cursor]) === self::CONSTRUCTOR_SMALLUINT) {
            ++$cursor;

            if ($cursor >= $listEnd) {
                throw MessageException::truncatedHeader();
            }

            return [ord($bytes[$cursor]), $cursor + 1];
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
     * @return array{0: array<string, mixed>, 1: int}
     */
    private function decodeDeliveryAnnotations(string $bytes, int $cursor): array
    {
        if (strlen($bytes) < $cursor + self::DELIVERY_ANNOTATIONS_DESCRIPTOR_LENGTH + 1) {
            throw MessageException::truncatedDeliveryAnnotations();
        }

        if (substr($bytes, $cursor, self::DELIVERY_ANNOTATIONS_DESCRIPTOR_LENGTH) !== self::DELIVERY_ANNOTATIONS_DESCRIPTOR) {
            throw MessageException::malformedDeliveryAnnotations();
        }

        $cursor += self::DELIVERY_ANNOTATIONS_DESCRIPTOR_LENGTH;

        return $this->decodeMessageMap(
            $bytes,
            $cursor,
            MessageException::malformedDeliveryAnnotations(...),
            MessageException::truncatedDeliveryAnnotations(...),
        );
    }

    /**
     * @param callable(): MessageException $malformed
     * @param callable(): MessageException $truncated
     */
    private function skipUnsupportedAnnotationValue(
        string $bytes,
        int $cursor,
        int $mapEnd,
        callable $malformed,
        callable $truncated,
    ): int {
        if ($cursor >= $mapEnd) {
            throw $truncated();
        }

        $constructor = ord($bytes[$cursor]);

        if ($constructor === self::CONSTRUCTOR_DESCRIBED) {
            $cursor = $this->skipUnsupportedAnnotationValue($bytes, $cursor + 1, $mapEnd, $malformed, $truncated);

            return $this->skipUnsupportedAnnotationValue($bytes, $cursor, $mapEnd, $malformed, $truncated);
        }

        $end = match ($constructor) {
            self::CONSTRUCTOR_NULL,
            self::CONSTRUCTOR_BOOL_TRUE,
            self::CONSTRUCTOR_BOOL_FALSE,
            self::CONSTRUCTOR_UINT0,
            self::CONSTRUCTOR_ULONG0,
            self::CONSTRUCTOR_LIST0 => $cursor + 1,
            self::CONSTRUCTOR_UBYTE,
            self::CONSTRUCTOR_SMALLUINT,
            self::CONSTRUCTOR_SMALLULONG,
            self::CONSTRUCTOR_SMALLINT,
            self::CONSTRUCTOR_SMALLLONG,
            self::CONSTRUCTOR_BOOL => $cursor + 2,
            self::CONSTRUCTOR_USHORT => $cursor + 3,
            self::CONSTRUCTOR_UINT,
            self::CONSTRUCTOR_FLOAT,
            self::CONSTRUCTOR_CHAR,
            self::CONSTRUCTOR_DECIMAL32 => $cursor + 5,
            self::CONSTRUCTOR_ULONG,
            self::CONSTRUCTOR_DOUBLE,
            self::CONSTRUCTOR_TIMESTAMP => $cursor + 9,
            self::CONSTRUCTOR_DECIMAL64 => $cursor + 9,
            self::CONSTRUCTOR_DECIMAL128 => $cursor + 17,
            self::CONSTRUCTOR_UUID => $cursor + 17,
            self::CONSTRUCTOR_STRING8,
            self::CONSTRUCTOR_SYMBOL8,
            self::CONSTRUCTOR_LIST8,
            self::CONSTRUCTOR_MAP8,
            self::CONSTRUCTOR_ARRAY8,
            self::CONSTRUCTOR_VBIN8 => $this->skipVariable8($bytes, $cursor, $mapEnd, $truncated),
            self::CONSTRUCTOR_STRING32,
            self::CONSTRUCTOR_SYMBOL32,
            self::CONSTRUCTOR_LIST32,
            self::CONSTRUCTOR_MAP32,
            self::CONSTRUCTOR_ARRAY32,
            self::CONSTRUCTOR_VBIN32 => $this->skipVariable32($bytes, $cursor, $mapEnd, $truncated),
            default => throw $malformed(),
        };

        if ($end > $mapEnd) {
            throw $truncated();
        }

        return $end;
    }

    /**
     * @param callable(): MessageException $truncated
     */
    private function skipVariable8(string $bytes, int $cursor, int $mapEnd, callable $truncated): int
    {
        if ($cursor + 2 > $mapEnd) {
            throw $truncated();
        }

        return $cursor + 2 + ord($bytes[$cursor + 1]);
    }

    /**
     * @param callable(): MessageException $truncated
     */
    private function skipVariable32(string $bytes, int $cursor, int $mapEnd, callable $truncated): int
    {
        if ($cursor + 5 > $mapEnd) {
            throw $truncated();
        }

        $length = (ord($bytes[$cursor + 1]) << 24)
            | (ord($bytes[$cursor + 2]) << 16)
            | (ord($bytes[$cursor + 3]) << 8)
            | ord($bytes[$cursor + 4]);

        return $cursor + 5 + $length;
    }

    /**
     * @return array{0: array<string, mixed>, 1: int}
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

        return $this->decodeMessageMap(
            $bytes,
            $cursor,
            MessageException::malformedMessageAnnotations(...),
            MessageException::truncatedMessageAnnotations(...),
        );
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

            $cursor = $this->skipPropertyValue($bytes, $cursor, $listEnd);
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
            return [
                null,
                $this->skipPropertyValue($bytes, $cursor, $listEnd),
            ];
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
            return [
                null,
                $this->skipPropertyValue($bytes, $cursor, $listEnd),
            ];
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

    private function skipPropertyValue(string $bytes, int $cursor, int $listEnd): int
    {
        return $this->skipUnsupportedAnnotationValue(
            $bytes,
            $cursor,
            $listEnd,
            MessageException::malformedProperties(...),
            MessageException::truncatedProperties(...),
        );
    }

    /**
     * @return array{0: array<string, mixed>, 1: int}
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

        return $this->decodeMessageMap(
            $bytes,
            $cursor,
            MessageException::malformedApplicationProperties(...),
            MessageException::truncatedApplicationProperties(...),
        );
    }

    /**
     * @return array{0: array<string, mixed>, 1: int}
     */
    private function decodeFooter(string $bytes, int $cursor): array
    {
        if (strlen($bytes) < $cursor + self::FOOTER_DESCRIPTOR_LENGTH + 1) {
            throw MessageException::truncatedFooter();
        }

        if (substr($bytes, $cursor, self::FOOTER_DESCRIPTOR_LENGTH) !== self::FOOTER_DESCRIPTOR) {
            throw MessageException::malformedFooter();
        }

        $cursor += self::FOOTER_DESCRIPTOR_LENGTH;

        return $this->decodeMessageMap(
            $bytes,
            $cursor,
            MessageException::malformedFooter(...),
            MessageException::truncatedFooter(...),
        );
    }

    /**
     * @param callable(): MessageException $malformed
     * @param callable(): MessageException $truncated
     *
     * @return array{0: array<string, mixed>, 1: int}
     */
    private function decodeMessageMap(
        string $bytes,
        int $cursor,
        callable $malformed,
        callable $truncated,
    ): array {
        try {
            [$map, $consumed] = (new Decoder())->decodeWithConsumed(substr($bytes, $cursor));
        } catch (DecodeException $exception) {
            if (str_starts_with($exception->getMessage(), 'Truncated AMQP')) {
                throw $truncated();
            }

            throw $malformed();
        }

        if (!$map instanceof AmqpMap) {
            throw $malformed();
        }

        $values = [];

        foreach ($map->pairs as [$key, $value]) {
            if ($key instanceof Symbol) {
                $key = $key->value;
            }

            if (!is_string($key) || $key === '') {
                throw $malformed();
            }

            $values[$key] = $value;
        }

        return [$values, $cursor + $consumed];
    }

}
