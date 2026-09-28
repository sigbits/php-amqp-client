<?php

declare(strict_types=1);

namespace Sigbits\Amqp\Protocol\Message;

use InvalidArgumentException;

final readonly class Properties
{
    public function __construct(
        public ?string $messageId = null,
        public ?string $correlationId = null,
        public ?string $contentType = null,
        public ?string $subject = null,
    ) {
        $this->assertString8($messageId, 'message ID');
        $this->assertString8($correlationId, 'correlation ID');
        $this->assertString8($contentType, 'content type');
        $this->assertString8($subject, 'subject');
    }

    private function assertString8(?string $value, string $label): void
    {
        if ($value !== null && strlen($value) > 255) {
            throw new InvalidArgumentException(sprintf('AMQP message %s must fit in string8/symbol8 encoding.', $label));
        }
    }
}
