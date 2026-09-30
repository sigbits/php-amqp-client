<?php

declare(strict_types=1);

namespace Sigbits\Amqp\Protocol\Message;

use InvalidArgumentException;
use Sigbits\Amqp\Protocol\Type\Byte;
use Sigbits\Amqp\Protocol\Type\Int_;
use Sigbits\Amqp\Protocol\Type\Long_;
use Sigbits\Amqp\Protocol\Type\Short;

final readonly class Message
{
    /**
     * @param array<string, string|Byte|Short|Int_|Long_> $deliveryAnnotations
     * @param array<string, string|Byte|Short|Int_|Long_> $messageAnnotations
     * @param array<string, string|Byte|Short|Int_|Long_> $applicationProperties
     * @param array<string, string|Byte|Short|Int_|Long_> $footer
     * @param list<string> $bodySequence
     */
    public function __construct(
        public string $body,
        public MessageBodySection $bodySection = MessageBodySection::Data,
        public array $bodySequence = [],
        public ?Header $header = null,
        public array $deliveryAnnotations = [],
        public array $messageAnnotations = [],
        public ?Properties $properties = null,
        public array $applicationProperties = [],
        public array $footer = [],
    ) {
        if ($bodySection === MessageBodySection::AmqpValue && strlen($body) > 255) {
            throw new InvalidArgumentException('AMQP value body must fit in string8 encoding.');
        }

        foreach ($bodySequence as $item) {
            if (strlen($item) > 255) {
                throw new InvalidArgumentException('AMQP sequence item must fit in string8 encoding.');
            }
        }

        foreach ($deliveryAnnotations as $name => $value) {
            if ($name === '') {
                throw new InvalidArgumentException('AMQP delivery annotation name must not be empty.');
            }

            if (strlen($name) > 255) {
                throw new InvalidArgumentException('AMQP delivery annotation name must fit in symbol8 encoding.');
            }

            if (is_string($value) && strlen($value) > 255) {
                throw new InvalidArgumentException('AMQP delivery annotation value must fit in string8 encoding.');
            }

            $this->assertSupportedMapValue($value, 'AMQP delivery annotation value');
        }

        foreach ($messageAnnotations as $name => $value) {
            if ($name === '') {
                throw new InvalidArgumentException('AMQP message annotation name must not be empty.');
            }

            if (strlen($name) > 255) {
                throw new InvalidArgumentException('AMQP message annotation name must fit in symbol8 encoding.');
            }

            if (is_string($value) && strlen($value) > 255) {
                throw new InvalidArgumentException('AMQP message annotation value must fit in string8 encoding.');
            }

            $this->assertSupportedMapValue($value, 'AMQP message annotation value');
        }

        foreach ($applicationProperties as $name => $value) {
            if ($name === '') {
                throw new InvalidArgumentException('AMQP application property name must not be empty.');
            }

            if (strlen($name) > 255) {
                throw new InvalidArgumentException('AMQP application property name must fit in symbol8 encoding.');
            }

            if (is_string($value) && strlen($value) > 255) {
                throw new InvalidArgumentException('AMQP application property value must fit in string8 encoding.');
            }

            $this->assertSupportedMapValue($value, 'AMQP application property value');
        }

        foreach ($footer as $name => $value) {
            if ($name === '') {
                throw new InvalidArgumentException('AMQP footer name must not be empty.');
            }

            if (strlen($name) > 255) {
                throw new InvalidArgumentException('AMQP footer name must fit in symbol8 encoding.');
            }

            if (is_string($value) && strlen($value) > 255) {
                throw new InvalidArgumentException('AMQP footer value must fit in string8 encoding.');
            }

            $this->assertSupportedMapValue($value, 'AMQP footer value');
        }
    }

    private function assertSupportedMapValue(mixed $value, string $label): void
    {
        if (
            is_string($value)
            || $value instanceof Byte
            || $value instanceof Short
            || $value instanceof Int_
            || $value instanceof Long_
        ) {
            return;
        }

        throw new InvalidArgumentException($label . ' must be a string or signed AMQP scalar value.');
    }
}
