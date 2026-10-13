<?php

declare(strict_types=1);

namespace Sigbits\Amqp\Protocol\Message;

use Sigbits\Amqp\Protocol\Codec\DecodeException;
use Sigbits\Amqp\Protocol\Codec\Decoder;
use Sigbits\Amqp\Protocol\Codec\Encoder;
use Sigbits\Amqp\Protocol\Type\AmqpList;
use Sigbits\Amqp\Protocol\Type\AmqpMap;
use Sigbits\Amqp\Protocol\Type\Binary;
use Sigbits\Amqp\Protocol\Type\Symbol;
use Sigbits\Amqp\Protocol\Type\Timestamp;
use Sigbits\Amqp\Protocol\Type\UInt;
use Sigbits\Amqp\Protocol\Type\ULong;
use Sigbits\Amqp\Protocol\Type\Uuid;

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
    private const int CONSTRUCTOR_BOOL = 0x56;
    private const int CONSTRUCTOR_BOOL_TRUE = 0x41;
    private const int CONSTRUCTOR_BOOL_FALSE = 0x42;
    private const int CONSTRUCTOR_UINT0 = 0x43;
    private const int CONSTRUCTOR_LIST0 = 0x45;
    private const int CONSTRUCTOR_LIST8 = 0xc0;
    private const int CONSTRUCTOR_NULL = 0x40;
    private const int CONSTRUCTOR_SMALLUINT = 0x52;
    private const int CONSTRUCTOR_UBYTE = 0x50;
    private const int CONSTRUCTOR_UINT = 0x70;
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

        $bodySection = null;
        $body = '';
        $bodySequence = [];
        $dataSections = [];
        $bodySequences = [];
        $bodyValue = new UnsetMessageBodyValue();
        $decodedBodySections = 0;

        while (true) {
            if (strlen($bytes) < $cursor + self::DATA_DESCRIPTOR_LENGTH + 1) {
                if ($decodedBodySections > 0) {
                    break;
                }

                throw MessageException::truncatedDataBody();
            }

            $bodyDescriptor = substr($bytes, $cursor, self::DATA_DESCRIPTOR_LENGTH);

            $currentBodySection = match ($bodyDescriptor) {
                self::DATA_DESCRIPTOR => MessageBodySection::Data,
                self::AMQP_SEQUENCE_DESCRIPTOR => MessageBodySection::AmqpSequence,
                self::AMQP_VALUE_DESCRIPTOR => MessageBodySection::AmqpValue,
                default => null,
            };

            if ($currentBodySection === null) {
                if ($decodedBodySections > 0) {
                    break;
                }

                throw MessageException::expectedDataBodyDescriptor();
            }

            if ($bodySection === null) {
                $bodySection = $currentBodySection;
            } elseif ($currentBodySection !== $bodySection) {
                throw MessageException::mixedBodySections();
            }

            $cursor += match ($currentBodySection) {
                MessageBodySection::Data => self::DATA_DESCRIPTOR_LENGTH,
                MessageBodySection::AmqpSequence => self::AMQP_SEQUENCE_DESCRIPTOR_LENGTH,
                MessageBodySection::AmqpValue => self::AMQP_VALUE_DESCRIPTOR_LENGTH,
            };

            if ($currentBodySection === MessageBodySection::Data) {
                [$dataSection, $cursor] = $this->decodeDataBody($bytes, $cursor);
                $dataSections[] = $dataSection;
            } elseif ($currentBodySection === MessageBodySection::AmqpSequence) {
                [$bodySequenceSection, $cursor] = $this->decodeAmqpSequenceBody($bytes, $cursor);
                $bodySequences[] = $bodySequenceSection;
            } else {
                [$body, $bodyValue, $cursor] = $this->decodeAmqpValueBody($bytes, $cursor);
            }

            ++$decodedBodySections;
        }

        if ($bodySection === MessageBodySection::Data) {
            $body = $dataSections[0];
        }

        if ($bodySection === MessageBodySection::AmqpSequence) {
            $bodySequence = $bodySequences[0];
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
            dataSections: $dataSections,
            bodySequences: $bodySequences,
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
            $properties->messageId,
            $properties->userId,
            $properties->to,
            $properties->subject,
            $properties->replyTo,
            $properties->correlationId,
            $properties->contentType === null ? null : new Symbol($properties->contentType),
            $properties->contentEncoding === null ? null : new Symbol($properties->contentEncoding),
            $properties->absoluteExpiryTime,
            $properties->creationTime,
            $properties->groupId,
            $properties->groupSequence,
            $properties->replyToGroupId,
        ];

        $lastSetField = -1;

        foreach ($fields as $index => $field) {
            if ($field !== null) {
                $lastSetField = $index;
            }
        }

        if ($lastSetField === -1) {
            return self::PROPERTIES_DESCRIPTOR . chr(self::CONSTRUCTOR_LIST0);
        }

        return self::PROPERTIES_DESCRIPTOR
            . (new Encoder())->encode(new AmqpList(array_slice($fields, 0, $lastSetField + 1)));
    }

    /**
     * @param array<string, mixed> $properties
     */
    private function encodeApplicationProperties(array $properties): string
    {
        return self::APPLICATION_PROPERTIES_DESCRIPTOR . $this->encodeMessageMap($properties);
    }

    /**
     * @param list<mixed> $items
     */
    private function encodeAmqpSequenceBody(array $items): string
    {
        return self::AMQP_SEQUENCE_DESCRIPTOR . (new Encoder())->encode(new AmqpList($items));
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
     * @return array{0: list<mixed>, 1: int}
     */
    private function decodeAmqpSequenceBody(string $bytes, int $cursor): array
    {
        try {
            [$list, $consumed] = (new Decoder())->decodeWithConsumed(substr($bytes, $cursor));
        } catch (DecodeException $exception) {
            if (str_starts_with($exception->getMessage(), 'Truncated AMQP')) {
                throw MessageException::truncatedDataBody();
            }

            throw MessageException::unsupportedAmqpSequenceEncoding();
        }

        if (!$list instanceof AmqpList) {
            throw MessageException::unsupportedAmqpSequenceEncoding();
        }

        return [$list->items, $cursor + $consumed];
    }

    /**
     * @return array{0: string, 1: int}
     */
    private function decodeDataBody(string $bytes, int $cursor): array
    {
        $bodyConstructor = ord($bytes[$cursor]);

        if (
            $bodyConstructor !== self::CONSTRUCTOR_VBIN8
            && $bodyConstructor !== self::CONSTRUCTOR_VBIN32
        ) {
            throw MessageException::unsupportedDataBodyEncoding();
        }

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

        return [substr($bytes, $cursor, $length), $cursor + $length];
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

        try {
            [$properties, $consumed] = (new Decoder())->decodeWithConsumed(substr($bytes, $cursor));
        } catch (DecodeException $exception) {
            if (str_starts_with($exception->getMessage(), 'Truncated AMQP')) {
                throw MessageException::truncatedProperties();
            }

            throw MessageException::malformedProperties();
        }

        if (!$properties instanceof AmqpList) {
            throw MessageException::malformedProperties();
        }

        $fields = $properties->items;

        return [
            new Properties(
                messageId: $this->nullableMessageId($fields[0] ?? null),
                correlationId: $this->nullableMessageId($fields[5] ?? null),
                contentType: $this->nullableSymbolValue($fields[6] ?? null),
                subject: $this->nullableString($fields[3] ?? null),
                userId: $this->nullableBinary($fields[1] ?? null),
                to: $this->nullableString($fields[2] ?? null),
                replyTo: $this->nullableString($fields[4] ?? null),
                contentEncoding: $this->nullableSymbolValue($fields[7] ?? null),
                absoluteExpiryTime: $this->nullableTimestamp($fields[8] ?? null),
                creationTime: $this->nullableTimestamp($fields[9] ?? null),
                groupId: $this->nullableString($fields[10] ?? null),
                groupSequence: $this->nullableUInt($fields[11] ?? null),
                replyToGroupId: $this->nullableString($fields[12] ?? null),
            ),
            $cursor + $consumed,
        ];
    }

    private function nullableString(mixed $value): ?string
    {
        return is_string($value) ? $value : null;
    }

    private function nullableSymbolValue(mixed $value): ?string
    {
        return $value instanceof Symbol ? $value->value : null;
    }

    /**
     * @return string|ULong|Uuid|Binary|null
     */
    private function nullableMessageId(mixed $value): string|ULong|Uuid|Binary|null
    {
        return is_string($value) || $value instanceof ULong || $value instanceof Uuid || $value instanceof Binary
            ? $value
            : null;
    }

    private function nullableBinary(mixed $value): ?Binary
    {
        return $value instanceof Binary ? $value : null;
    }

    private function nullableTimestamp(mixed $value): ?Timestamp
    {
        return $value instanceof Timestamp ? $value : null;
    }

    private function nullableUInt(mixed $value): ?UInt
    {
        return $value instanceof UInt ? $value : null;
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
