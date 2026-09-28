<?php

declare(strict_types=1);

namespace Sigbits\Amqp\Protocol\Frame;

final class FrameHeaderCodec
{
    public function encode(FrameHeader $header): string
    {
        return pack('NCCn', $header->size, $header->dataOffset, $header->type, $header->channel);
    }

    public function decode(string $bytes): FrameHeader
    {
        if (strlen($bytes) < FrameHeader::LENGTH) {
            throw FrameException::truncatedHeader();
        }

        $values = unpack('Nsize/CdataOffset/Ctype/nchannel', substr($bytes, 0, FrameHeader::LENGTH));

        return new FrameHeader(
            size: $values['size'],
            dataOffset: $values['dataOffset'],
            type: $values['type'],
            channel: $values['channel'],
        );
    }
}
