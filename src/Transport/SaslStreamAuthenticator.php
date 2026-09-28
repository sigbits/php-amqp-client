<?php

declare(strict_types=1);

namespace Sigbits\Amqp\Transport;

use Closure;
use Sigbits\Amqp\Protocol\Frame\Frame;
use Sigbits\Amqp\Protocol\Frame\FrameHeader;
use Sigbits\Amqp\Protocol\Frame\FrameHeaderCodec;
use Sigbits\Amqp\Protocol\Header\ProtocolHeader;
use Sigbits\Amqp\Protocol\Header\ProtocolHeaderCodec;
use Sigbits\Amqp\Protocol\Sasl\SaslClient;
use Sigbits\Amqp\Protocol\Sasl\SaslInitCodec;
use Sigbits\Amqp\Protocol\Sasl\SaslMechanismsCodec;
use Sigbits\Amqp\Protocol\Sasl\SaslOutcomeCodec;

final readonly class SaslStreamAuthenticator
{
    private const int SASL_FRAME_TYPE = 1;

    /**
     * @param null|Closure(mixed, int): string $reader
     * @param null|Closure(mixed, string): int $writer
     */
    public function __construct(
        private ?Closure $reader = null,
        private ?Closure $writer = null,
        private ProtocolHeaderCodec $headerCodec = new ProtocolHeaderCodec(),
        private FrameHeaderCodec $frameHeaderCodec = new FrameHeaderCodec(),
        private SaslMechanismsCodec $mechanismsCodec = new SaslMechanismsCodec(),
        private SaslInitCodec $initCodec = new SaslInitCodec(),
        private SaslOutcomeCodec $outcomeCodec = new SaslOutcomeCodec(),
    ) {
    }

    public function authenticate(mixed $stream, SaslClient $client): void
    {
        $this->write($stream, $this->headerCodec->encode(ProtocolHeader::sasl()));
        $this->headerCodec->decode($this->read($stream, 8));

        $mechanisms = $this->mechanismsCodec->decode($this->readFrame($stream)->payload);
        $this->writeFrame($stream, $this->initCodec->encode($client->init($mechanisms)));

        $outcome = $this->outcomeCodec->decode($this->readFrame($stream)->payload);
        $client->complete($outcome);
    }

    private function readFrame(mixed $stream): Frame
    {
        $headerBytes = $this->read($stream, FrameHeader::LENGTH);
        $header = $this->frameHeaderCodec->decode($headerBytes);

        if ($header->type !== self::SASL_FRAME_TYPE) {
            throw TransportException::unexpectedFrameType($header->type);
        }

        $payload = $this->read($stream, $header->payloadLength());

        return new Frame($header, $payload);
    }

    private function writeFrame(mixed $stream, string $payload): void
    {
        $header = new FrameHeader(
            size: FrameHeader::LENGTH + strlen($payload),
            dataOffset: 2,
            type: self::SASL_FRAME_TYPE,
            channel: 0,
        );

        $this->write($stream, $this->frameHeaderCodec->encode($header) . $payload);
    }

    private function read(mixed $stream, int $length): string
    {
        $bytes = '';

        while (strlen($bytes) < $length) {
            $chunk = $this->reader !== null
                ? ($this->reader)($stream, $length - strlen($bytes))
                : fread($stream, $length - strlen($bytes));

            if ($chunk === false || $chunk === '') {
                throw TransportException::unexpectedEndOfStream();
            }

            $bytes .= $chunk;
        }

        return $bytes;
    }

    private function write(mixed $stream, string $bytes): void
    {
        $written = $this->writer !== null
            ? ($this->writer)($stream, $bytes)
            : fwrite($stream, $bytes);

        if ($written !== strlen($bytes)) {
            throw TransportException::writeFailed();
        }
    }
}
