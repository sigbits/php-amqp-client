<?php

declare(strict_types=1);

namespace Sigbits\Amqp\Protocol\Message;

use Sigbits\Amqp\Protocol\Type\Binary;
use Sigbits\Amqp\Protocol\Type\Timestamp;
use Sigbits\Amqp\Protocol\Type\UInt;
use Sigbits\Amqp\Protocol\Type\ULong;
use Sigbits\Amqp\Protocol\Type\Uuid;

final readonly class Properties
{
    /**
     * @param string|ULong|Uuid|Binary|null $messageId
     * @param string|ULong|Uuid|Binary|null $correlationId
     */
    public function __construct(
        public string|ULong|Uuid|Binary|null $messageId = null,
        public string|ULong|Uuid|Binary|null $correlationId = null,
        public ?string $contentType = null,
        public ?string $subject = null,
        public ?Binary $userId = null,
        public ?string $to = null,
        public ?string $replyTo = null,
        public ?string $contentEncoding = null,
        public ?Timestamp $absoluteExpiryTime = null,
        public ?Timestamp $creationTime = null,
        public ?string $groupId = null,
        public ?UInt $groupSequence = null,
        public ?string $replyToGroupId = null,
    ) {
    }
}
