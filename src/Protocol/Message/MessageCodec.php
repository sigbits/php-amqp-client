<?php

declare(strict_types=1);

namespace Sigbits\Amqp\Protocol\Message;

final class MessageCodec
{
    private const string DATA_DESCRIPTOR = "\x00\x53\x75";
    private const int DATA_DESCRIPTOR_LENGTH = 3;
    private const int CONSTRUCTOR_VBIN8 = 0xa0;

    public function encode(Message $message): string
    {
        return self::DATA_DESCRIPTOR
            . chr(self::CONSTRUCTOR_VBIN8)
            . chr(strlen($message->body))
            . $message->body;
    }

    public function decode(string $bytes): Message
    {
        if (strlen($bytes) < self::DATA_DESCRIPTOR_LENGTH + 1) {
            throw MessageException::truncatedDataBody();
        }

        if (substr($bytes, 0, self::DATA_DESCRIPTOR_LENGTH) !== self::DATA_DESCRIPTOR) {
            throw MessageException::expectedDataBodyDescriptor();
        }

        $cursor = self::DATA_DESCRIPTOR_LENGTH;

        if (ord($bytes[$cursor]) !== self::CONSTRUCTOR_VBIN8) {
            throw MessageException::unsupportedDataBodyEncoding();
        }

        ++$cursor;

        if (strlen($bytes) <= $cursor) {
            throw MessageException::truncatedDataBody();
        }

        $length = ord($bytes[$cursor]);
        ++$cursor;

        if (strlen($bytes) < $cursor + $length) {
            throw MessageException::truncatedDataBody();
        }

        return new Message(substr($bytes, $cursor, $length));
    }
}
