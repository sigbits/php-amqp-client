<?php

declare(strict_types=1);

namespace Sigbits\Amqp\Protocol\Frame;

final class FrameParser
{
    private string $buffer = '';

    public function __construct(
        private readonly int $maxFrameSize = 1024 * 1024,
        private readonly FrameHeaderCodec $headerCodec = new FrameHeaderCodec(),
    ) {
    }

    /**
     * @return list<Frame>
     */
    public function push(string $bytes): array
    {
        $this->buffer .= $bytes;
        $frames = [];

        while (strlen($this->buffer) >= FrameHeader::LENGTH) {
            $header = $this->headerCodec->decode($this->buffer);

            if ($header->size > $this->maxFrameSize) {
                throw FrameException::frameSizeExceedsMaximum();
            }

            if (strlen($this->buffer) < $header->size) {
                break;
            }

            $frameBytes = substr($this->buffer, 0, $header->size);
            $this->buffer = substr($this->buffer, $header->size);

            $payloadOffset = $header->dataOffset * 4;
            $frames[] = new Frame(
                $header,
                substr($frameBytes, $payloadOffset),
            );
        }

        return $frames;
    }

    public function finish(): void
    {
        if ($this->buffer === '') {
            return;
        }

        if (strlen($this->buffer) < FrameHeader::LENGTH) {
            throw FrameException::truncatedHeader();
        }

        $header = $this->headerCodec->decode($this->buffer);

        if ($header->size > $this->maxFrameSize) {
            throw FrameException::frameSizeExceedsMaximum();
        }

        throw FrameException::truncatedPayload();
    }
}
