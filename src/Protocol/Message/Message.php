<?php

declare(strict_types=1);

namespace Sigbits\Amqp\Protocol\Message;

use InvalidArgumentException;

final readonly class Message
{
    /**
     * @param array<string, string> $deliveryAnnotations
     * @param array<string, string> $messageAnnotations
     * @param array<string, string> $applicationProperties
     * @param array<string, string> $footer
     */
    public function __construct(
        public string $body,
        public MessageBodySection $bodySection = MessageBodySection::Data,
        public ?Header $header = null,
        public array $deliveryAnnotations = [],
        public array $messageAnnotations = [],
        public ?Properties $properties = null,
        public array $applicationProperties = [],
        public array $footer = [],
    ) {
        if (strlen($body) > 255) {
            throw new InvalidArgumentException('AMQP message body must fit in string8/vbin8 encoding.');
        }

        foreach ($deliveryAnnotations as $name => $value) {
            if ($name === '') {
                throw new InvalidArgumentException('AMQP delivery annotation name must not be empty.');
            }

            if (strlen($name) > 255) {
                throw new InvalidArgumentException('AMQP delivery annotation name must fit in symbol8 encoding.');
            }

            if (strlen($value) > 255) {
                throw new InvalidArgumentException('AMQP delivery annotation value must fit in string8 encoding.');
            }
        }

        foreach ($messageAnnotations as $name => $value) {
            if ($name === '') {
                throw new InvalidArgumentException('AMQP message annotation name must not be empty.');
            }

            if (strlen($name) > 255) {
                throw new InvalidArgumentException('AMQP message annotation name must fit in symbol8 encoding.');
            }

            if (strlen($value) > 255) {
                throw new InvalidArgumentException('AMQP message annotation value must fit in string8 encoding.');
            }
        }

        foreach ($applicationProperties as $name => $value) {
            if ($name === '') {
                throw new InvalidArgumentException('AMQP application property name must not be empty.');
            }

            if (strlen($name) > 255) {
                throw new InvalidArgumentException('AMQP application property name must fit in symbol8 encoding.');
            }

            if (strlen($value) > 255) {
                throw new InvalidArgumentException('AMQP application property value must fit in string8 encoding.');
            }
        }

        foreach ($footer as $name => $value) {
            if ($name === '') {
                throw new InvalidArgumentException('AMQP footer name must not be empty.');
            }

            if (strlen($name) > 255) {
                throw new InvalidArgumentException('AMQP footer name must fit in symbol8 encoding.');
            }

            if (strlen($value) > 255) {
                throw new InvalidArgumentException('AMQP footer value must fit in string8 encoding.');
            }
        }
    }
}
