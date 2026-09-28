<?php

declare(strict_types=1);

namespace Sigbits\Amqp\Engine;

use Sigbits\Amqp\Protocol\Frame\Frame;
use Sigbits\Amqp\Protocol\Frame\FrameHeader;
use Sigbits\Amqp\Protocol\Frame\FrameHeaderCodec;
use Sigbits\Amqp\Protocol\Frame\FrameParser;
use Sigbits\Amqp\Protocol\Performative\Begin;
use Sigbits\Amqp\Protocol\Performative\BeginCodec;
use Sigbits\Amqp\Protocol\Performative\End;
use Sigbits\Amqp\Protocol\Performative\EndCodec;

final class SessionEngine
{
    private const int DEFAULT_WINDOW = 2_147_483_647;

    private SessionState $state = SessionState::Idle;

    public function __construct(
        private readonly int $localChannel,
        private readonly FrameHeaderCodec $frameHeaderCodec = new FrameHeaderCodec(),
        private readonly FrameParser $frameParser = new FrameParser(),
        private readonly BeginCodec $beginCodec = new BeginCodec(),
        private readonly EndCodec $endCodec = new EndCodec(),
    ) {
    }

    public function state(): SessionState
    {
        return $this->state;
    }

    /**
     * @return list<string>
     */
    public function begin(): array
    {
        if ($this->state !== SessionState::Idle) {
            throw SessionException::cannotBegin($this->state);
        }

        $this->state = SessionState::BeginSent;

        return [
            $this->frame($this->beginCodec->encode(new Begin(
                remoteChannel: null,
                nextOutgoingId: 0,
                incomingWindow: self::DEFAULT_WINDOW,
                outgoingWindow: self::DEFAULT_WINDOW,
            ))),
        ];
    }

    /**
     * @return list<SessionEvent>
     */
    public function push(string $bytes): array
    {
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
    public function end(): array
    {
        if ($this->state !== SessionState::Mapped) {
            throw SessionException::cannotEnd($this->state);
        }

        $this->state = SessionState::EndSent;

        return [
            $this->frame($this->endCodec->encode(new End())),
        ];
    }

    private function frame(string $payload): string
    {
        return $this->frameHeaderCodec->encode(new FrameHeader(
            size: FrameHeader::LENGTH + strlen($payload),
            dataOffset: 2,
            type: 0,
            channel: $this->localChannel,
        )) . $payload;
    }

    /**
     * @return list<SessionEvent>
     */
    private function handleFrame(Frame $frame): array
    {
        if (str_starts_with($frame->payload, "\x00\x53\x11")) {
            $this->beginCodec->decode($frame->payload);
            $this->state = SessionState::Mapped;

            return [SessionEvent::SessionMapped];
        }

        if (str_starts_with($frame->payload, "\x00\x53\x17")) {
            $this->endCodec->decode($frame->payload);
            $this->state = SessionState::Ended;

            return [SessionEvent::SessionEnded];
        }

        throw SessionException::unsupportedSessionPerformative();
    }
}
