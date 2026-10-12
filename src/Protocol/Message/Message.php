<?php

declare(strict_types=1);

namespace Sigbits\Amqp\Protocol\Message;

use InvalidArgumentException;

final readonly class Message
{
    public mixed $bodyValue;

    private bool $hasBodyValue;

    /**
     * @param array<string, mixed> $deliveryAnnotations
     * @param array<string, mixed> $messageAnnotations
     * @param array<string, mixed> $applicationProperties
     * @param array<string, mixed> $footer
     * @param list<string> $bodySequence
     * @param mixed $bodyValue AMQP value body payload when provided. Use `hasBodyValue()` to distinguish an
     *                         explicit AMQP null from the legacy string body path.
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
        mixed $bodyValue = new UnsetMessageBodyValue(),
    ) {
        $this->hasBodyValue = !$bodyValue instanceof UnsetMessageBodyValue;
        $this->bodyValue = $this->hasBodyValue ? $bodyValue : null;

        if ($bodySection === MessageBodySection::AmqpValue && !$this->hasBodyValue && strlen($body) > 255) {
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
        }

        foreach ($messageAnnotations as $name => $value) {
            if ($name === '') {
                throw new InvalidArgumentException('AMQP message annotation name must not be empty.');
            }
        }

        foreach ($applicationProperties as $name => $value) {
            if ($name === '') {
                throw new InvalidArgumentException('AMQP application property name must not be empty.');
            }
        }

        foreach ($footer as $name => $value) {
            if ($name === '') {
                throw new InvalidArgumentException('AMQP footer name must not be empty.');
            }
        }
    }

    public function hasBodyValue(): bool
    {
        return $this->hasBodyValue;
    }
}
