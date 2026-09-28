<?php

declare(strict_types=1);

namespace Sigbits\Amqp\Protocol\Frame;

use InvalidArgumentException;

final readonly class FrameHeader
{
    public const int LENGTH = 8;

    public function __construct(
        public int $size,
        public int $dataOffset,
        public int $type,
        public int $channel,
    ) {
        if ($size < self::LENGTH) {
            throw FrameException::frameSizeTooSmall();
        }

        if ($dataOffset < 2) {
            throw FrameException::dataOffsetTooSmall();
        }

        if ($dataOffset * 4 > $size) {
            throw FrameException::dataOffsetExceedsFrameSize();
        }

        if ($type < 0 || $type > 255) {
            throw new InvalidArgumentException('AMQP frame type must be between 0 and 255.');
        }

        if ($channel < 0 || $channel > 65535) {
            throw new InvalidArgumentException('AMQP frame channel must be between 0 and 65535.');
        }
    }

    public function extendedHeaderLength(): int
    {
        return ($this->dataOffset * 4) - self::LENGTH;
    }

    public function payloadLength(): int
    {
        return $this->size - ($this->dataOffset * 4);
    }
}
