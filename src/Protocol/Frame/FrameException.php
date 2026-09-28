<?php

declare(strict_types=1);

namespace Sigbits\Amqp\Protocol\Frame;

use RuntimeException;

final class FrameException extends RuntimeException
{
    public static function truncatedHeader(): self
    {
        return new self('Truncated AMQP frame header.');
    }

    public static function frameSizeTooSmall(): self
    {
        return new self('AMQP frame size must be at least 8 bytes.');
    }

    public static function dataOffsetTooSmall(): self
    {
        return new self('AMQP frame data offset must be at least 2.');
    }

    public static function dataOffsetExceedsFrameSize(): self
    {
        return new self('AMQP frame data offset exceeds frame size.');
    }

    public static function frameSizeExceedsMaximum(): self
    {
        return new self('AMQP frame size exceeds configured maximum.');
    }
}
