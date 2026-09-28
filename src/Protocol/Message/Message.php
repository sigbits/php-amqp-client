<?php

declare(strict_types=1);

namespace Sigbits\Amqp\Protocol\Message;

use InvalidArgumentException;

final readonly class Message
{
    /**
     * @param array<string, string> $messageAnnotations
     * @param array<string, string> $applicationProperties
     */
    public function __construct(
        public string $body,
        public ?Header $header = null,
        public array $messageAnnotations = [],
        public ?Properties $properties = null,
        public array $applicationProperties = [],
    ) {
        if (strlen($body) > 255) {
            throw new InvalidArgumentException('AMQP message body must fit in vbin8 data encoding.');
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
    }
}
