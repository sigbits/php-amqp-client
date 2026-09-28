<?php

declare(strict_types=1);

namespace Sigbits\Amqp\Protocol\Frame;

final readonly class Frame
{
    public function __construct(
        public FrameHeader $header,
        public string $payload,
    ) {
    }
}
