<?php

declare(strict_types=1);

namespace Sigbits\Amqp\Engine;

use Sigbits\Amqp\Protocol\Frame\Frame;
use Sigbits\Amqp\Protocol\Frame\FrameHeader;
use Sigbits\Amqp\Protocol\Frame\FrameHeaderCodec;
use Sigbits\Amqp\Protocol\Frame\FrameParser;
use Sigbits\Amqp\Protocol\Message\Message;
use Sigbits\Amqp\Protocol\Message\MessageCodec;
use Sigbits\Amqp\Protocol\Performative\Attach;
use Sigbits\Amqp\Protocol\Performative\AttachCodec;
use Sigbits\Amqp\Protocol\Performative\Detach;
use Sigbits\Amqp\Protocol\Performative\DetachCodec;
use Sigbits\Amqp\Protocol\Performative\FlowCodec;
use Sigbits\Amqp\Protocol\Performative\LinkRole;
use Sigbits\Amqp\Protocol\Performative\Transfer;
use Sigbits\Amqp\Protocol\Performative\TransferCodec;

final class SenderLinkEngine
{
    private SenderLinkState $state = SenderLinkState::Idle;
    private int $nextDeliveryId = 0;
    private int $availableCredit = 0;

    public function __construct(
        private readonly int $sessionChannel,
        private readonly string $name,
        private readonly int $handle,
        private readonly ?string $targetAddress = null,
        private readonly FrameHeaderCodec $frameHeaderCodec = new FrameHeaderCodec(),
        private readonly FrameParser $frameParser = new FrameParser(),
        private readonly AttachCodec $attachCodec = new AttachCodec(),
        private readonly DetachCodec $detachCodec = new DetachCodec(),
        private readonly FlowCodec $flowCodec = new FlowCodec(),
        private readonly TransferCodec $transferCodec = new TransferCodec(),
        private readonly MessageCodec $messageCodec = new MessageCodec(),
    ) {
    }

    public function state(): SenderLinkState
    {
        return $this->state;
    }

    public function availableCredit(): int
    {
        return $this->availableCredit;
    }

    public function claimCredit(): int
    {
        if ($this->availableCredit <= 0) {
            throw SenderLinkException::noCreditAvailable();
        }

        $deliveryId = $this->nextDeliveryId;
        ++$this->nextDeliveryId;
        --$this->availableCredit;

        return $deliveryId;
    }

    /**
     * @return list<string>
     */
    public function transfer(Message $message, int $maxFrameSize = 512): array
    {
        $deliveryTag = sprintf('delivery-%d', $this->nextDeliveryId);
        $transferOverhead = strlen($this->transferCodec->encode(new Transfer(
            handle: $this->handle,
            deliveryId: $this->nextDeliveryId,
            deliveryTag: $deliveryTag,
            messageFormat: 0,
            more: true,
        )));
        $maxPayloadChunkSize = $maxFrameSize - FrameHeader::LENGTH - $transferOverhead;

        if ($maxPayloadChunkSize < 1) {
            throw SenderLinkException::transferFrameSizeTooSmall();
        }

        $deliveryId = $this->claimCredit();
        $messageBytes = $this->messageCodec->encode($message);
        $messageChunks = str_split($messageBytes, $maxPayloadChunkSize);
        $frames = [];
        $lastChunkIndex = count($messageChunks) - 1;

        foreach ($messageChunks as $index => $messageChunk) {
            $more = $index < $lastChunkIndex;
            $frames[] = $this->frame(
                $this->transferCodec->encode(new Transfer(
                    handle: $this->handle,
                    deliveryId: $deliveryId,
                    deliveryTag: $deliveryTag,
                    messageFormat: 0,
                    more: $more,
                )) . $messageChunk,
            );
        }

        return $frames;
    }

    /**
     * @return list<string>
     */
    public function attach(): array
    {
        if ($this->state !== SenderLinkState::Idle) {
            throw SenderLinkException::cannotAttach($this->state);
        }

        $this->state = SenderLinkState::AttachSent;

        return [
            $this->frame($this->attachCodec->encode(new Attach(
                name: $this->name,
                handle: $this->handle,
                role: LinkRole::Sender,
                targetAddress: $this->targetAddress,
                initialDeliveryCount: $this->targetAddress === null ? null : 0,
            ))),
        ];
    }

    /**
     * @return list<SenderLinkEvent>
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
        if ($this->state !== SenderLinkState::Attached) {
            throw SenderLinkException::cannotDetach($this->state);
        }

        $this->state = SenderLinkState::DetachSent;

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
     * @return list<SenderLinkEvent>
     */
    private function handleFrame(Frame $frame): array
    {
        if (str_starts_with($frame->payload, "\x00\x53\x12")) {
            $this->attachCodec->decode($frame->payload);
            $this->state = SenderLinkState::Attached;

            return [SenderLinkEvent::LinkAttached];
        }

        if (str_starts_with($frame->payload, "\x00\x53\x16")) {
            $this->detachCodec->decode($frame->payload);
            $this->state = SenderLinkState::Detached;

            return [SenderLinkEvent::LinkDetached];
        }

        if (str_starts_with($frame->payload, "\x00\x53\x13")) {
            $flow = $this->flowCodec->decode($frame->payload);
            $creditLimit = $flow->deliveryCount + $flow->linkCredit;
            $this->availableCredit = max(0, $creditLimit - $this->nextDeliveryId);

            return [SenderLinkEvent::LinkCreditUpdated];
        }

        throw SenderLinkException::unsupportedSenderLinkPerformative();
    }
}
