<?php

declare(strict_types=1);

namespace Sigbits\Amqp\Engine;

use Sigbits\Amqp\Protocol\Frame\Frame;
use Sigbits\Amqp\Protocol\Frame\FrameHeader;
use Sigbits\Amqp\Protocol\Frame\FrameHeaderCodec;
use Sigbits\Amqp\Protocol\Frame\FrameParser;
use Sigbits\Amqp\Protocol\Header\ProtocolHeader;
use Sigbits\Amqp\Protocol\Header\ProtocolHeaderCodec;
use Sigbits\Amqp\Protocol\Performative\Close;
use Sigbits\Amqp\Protocol\Performative\CloseCodec;
use Sigbits\Amqp\Protocol\Performative\Open;
use Sigbits\Amqp\Protocol\Performative\OpenCodec;

final class ConnectionEngine
{
    private ConnectionState $state = ConnectionState::Idle;
    private string $headerBuffer = '';
    private bool $remoteHeaderReceived = false;

    public function __construct(
        private readonly string $localContainerId,
        private readonly ProtocolHeaderCodec $protocolHeaderCodec = new ProtocolHeaderCodec(),
        private readonly FrameHeaderCodec $frameHeaderCodec = new FrameHeaderCodec(),
        private readonly FrameParser $frameParser = new FrameParser(),
        private readonly OpenCodec $openCodec = new OpenCodec(),
        private readonly CloseCodec $closeCodec = new CloseCodec(),
    ) {
    }

    public function state(): ConnectionState
    {
        return $this->state;
    }

    /**
     * @return list<string>
     */
    public function start(): array
    {
        $this->state = ConnectionState::OpenSent;

        return [
            $this->protocolHeaderCodec->encode(ProtocolHeader::amqp()),
            $this->frame($this->openCodec->encode(new Open($this->localContainerId))),
        ];
    }

    /**
     * @return list<ConnectionEvent>
     */
    public function push(string $bytes): array
    {
        if (!$this->remoteHeaderReceived) {
            $this->headerBuffer .= $bytes;

            if (strlen($this->headerBuffer) < 8) {
                return [];
            }

            $this->protocolHeaderCodec->decode(substr($this->headerBuffer, 0, 8));
            $bytes = substr($this->headerBuffer, 8);
            $this->headerBuffer = '';
            $this->remoteHeaderReceived = true;
        }

        $events = [];

        foreach ($this->frameParser->push($bytes) as $frame) {
            foreach ($this->handleFrame($frame) as $event) {
                $events[] = $event;
            }
        }

        return $events;
    }

    /**
     * @return list<string>
     */
    public function close(): array
    {
        $this->state = ConnectionState::CloseSent;

        return [
            $this->frame($this->closeCodec->encode(new Close())),
        ];
    }

    private function frame(string $payload): string
    {
        return $this->frameHeaderCodec->encode(new FrameHeader(
            size: FrameHeader::LENGTH + strlen($payload),
            dataOffset: 2,
            type: 0,
            channel: 0,
        )) . $payload;
    }

    /**
     * @return list<ConnectionEvent>
     */
    private function handleFrame(Frame $frame): array
    {
        if (str_starts_with($frame->payload, "\x00\x53\x10")) {
            $this->openCodec->decode($frame->payload);
            $this->state = ConnectionState::Opened;

            return [ConnectionEvent::ConnectionOpened];
        }

        if (str_starts_with($frame->payload, "\x00\x53\x18")) {
            $this->closeCodec->decode($frame->payload);
            $this->state = ConnectionState::Closed;

            return [ConnectionEvent::ConnectionClosed];
        }

        return [];
    }
}
