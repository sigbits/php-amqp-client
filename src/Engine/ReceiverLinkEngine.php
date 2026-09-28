<?php

declare(strict_types=1);

namespace Sigbits\Amqp\Engine;

use Sigbits\Amqp\Protocol\Frame\Frame;
use Sigbits\Amqp\Protocol\Frame\FrameHeader;
use Sigbits\Amqp\Protocol\Frame\FrameHeaderCodec;
use Sigbits\Amqp\Protocol\Frame\FrameParser;
use Sigbits\Amqp\Protocol\Performative\Attach;
use Sigbits\Amqp\Protocol\Performative\AttachCodec;
use Sigbits\Amqp\Protocol\Performative\Detach;
use Sigbits\Amqp\Protocol\Performative\DetachCodec;
use Sigbits\Amqp\Protocol\Performative\LinkRole;

final class ReceiverLinkEngine
{
    private ReceiverLinkState $state = ReceiverLinkState::Idle;

    public function __construct(
        private readonly int $sessionChannel,
        private readonly string $name,
        private readonly int $handle,
        private readonly FrameHeaderCodec $frameHeaderCodec = new FrameHeaderCodec(),
        private readonly FrameParser $frameParser = new FrameParser(),
        private readonly AttachCodec $attachCodec = new AttachCodec(),
        private readonly DetachCodec $detachCodec = new DetachCodec(),
    ) {
    }

    public function state(): ReceiverLinkState
    {
        return $this->state;
    }

    /**
     * @return list<string>
     */
    public function attach(): array
    {
        if ($this->state !== ReceiverLinkState::Idle) {
            throw ReceiverLinkException::cannotAttach($this->state);
        }

        $this->state = ReceiverLinkState::AttachSent;

        return [
            $this->frame($this->attachCodec->encode(new Attach(
                name: $this->name,
                handle: $this->handle,
                role: LinkRole::Receiver,
            ))),
        ];
    }

    /**
     * @return list<ReceiverLinkEvent>
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
    public function detach(): array
    {
        if ($this->state !== ReceiverLinkState::Attached) {
            throw ReceiverLinkException::cannotDetach($this->state);
        }

        $this->state = ReceiverLinkState::DetachSent;

        return [
            $this->frame($this->detachCodec->encode(new Detach(handle: $this->handle))),
        ];
    }

    private function frame(string $payload): string
    {
        return $this->frameHeaderCodec->encode(new FrameHeader(
            size: FrameHeader::LENGTH + strlen($payload),
            dataOffset: 2,
            type: 0,
            channel: $this->sessionChannel,
        )) . $payload;
    }

    /**
     * @return list<ReceiverLinkEvent>
     */
    private function handleFrame(Frame $frame): array
    {
        if (str_starts_with($frame->payload, "\x00\x53\x12")) {
            $this->attachCodec->decode($frame->payload);
            $this->state = ReceiverLinkState::Attached;

            return [ReceiverLinkEvent::LinkAttached];
        }

        if (str_starts_with($frame->payload, "\x00\x53\x16")) {
            $this->detachCodec->decode($frame->payload);
            $this->state = ReceiverLinkState::Detached;

            return [ReceiverLinkEvent::LinkDetached];
        }

        throw ReceiverLinkException::unsupportedReceiverLinkPerformative();
    }
}
