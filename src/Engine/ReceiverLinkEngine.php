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
use Sigbits\Amqp\Protocol\Performative\Disposition;
use Sigbits\Amqp\Protocol\Performative\DispositionCodec;
use Sigbits\Amqp\Protocol\Performative\Flow;
use Sigbits\Amqp\Protocol\Performative\FlowCodec;
use Sigbits\Amqp\Protocol\Performative\LinkRole;
use Sigbits\Amqp\Protocol\Performative\PerformativeError;
use Sigbits\Amqp\Protocol\Performative\SettlementOutcome;
use Sigbits\Amqp\Protocol\Performative\TransferCodec;

final class ReceiverLinkEngine
{
    private const int DEFAULT_INCOMING_WINDOW = 2_147_483_647;

    private ReceiverLinkState $state = ReceiverLinkState::Idle;
    private string $incomingTransferPayload = '';
    private ?int $incomingTransferDeliveryId = null;
    private ?PerformativeError $lastRemoteDetachError = null;

    /**
     * @var list<ReceivedDelivery>
     */
    private array $receivedDeliveries = [];

    /**
     * @var array<int, true>
     */
    private array $unsettledDeliveries = [];

    public function __construct(
        private readonly int $sessionChannel,
        private readonly string $name,
        private readonly int $handle,
        private readonly ?string $sourceAddress = null,
        private readonly ?string $targetAddress = null,
        private readonly FrameHeaderCodec $frameHeaderCodec = new FrameHeaderCodec(),
        private readonly FrameParser $frameParser = new FrameParser(),
        private readonly AttachCodec $attachCodec = new AttachCodec(),
        private readonly DetachCodec $detachCodec = new DetachCodec(),
        private readonly FlowCodec $flowCodec = new FlowCodec(),
        private readonly TransferCodec $transferCodec = new TransferCodec(),
        private readonly MessageCodec $messageCodec = new MessageCodec(),
        private readonly DispositionCodec $dispositionCodec = new DispositionCodec(),
    ) {
    }

    public function state(): ReceiverLinkState
    {
        return $this->state;
    }

    public function receive(): ?Message
    {
        return $this->receiveDelivery()?->message;
    }

    public function receiveDelivery(): ?ReceivedDelivery
    {
        return array_shift($this->receivedDeliveries);
    }

    public function lastRemoteDetachError(): ?PerformativeError
    {
        return $this->lastRemoteDetachError;
    }

    /**
     * @return list<string>
     */
    public function grantCredit(int $deliveryCount, int $linkCredit): array
    {
        return [
            $this->frame($this->flowCodec->encode(new Flow(
                handle: $this->handle,
                deliveryCount: $deliveryCount,
                linkCredit: $linkCredit,
                incomingWindow: self::DEFAULT_INCOMING_WINDOW,
                nextOutgoingId: 0,
                outgoingWindow: self::DEFAULT_INCOMING_WINDOW,
            ))),
        ];
    }

    /**
     * @return list<string>
     */
    public function accept(int $deliveryId): array
    {
        return $this->settle($deliveryId, SettlementOutcome::Accepted);
    }

    /**
     * @return list<string>
     */
    public function release(int $deliveryId): array
    {
        return $this->settle($deliveryId, SettlementOutcome::Released);
    }

    /**
     * @return list<string>
     */
    public function reject(int $deliveryId): array
    {
        return $this->settle($deliveryId, SettlementOutcome::Rejected);
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
                sourceAddress: $this->sourceAddress,
                targetAddress: $this->targetAddress,
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

    public function finish(): void
    {
        $this->frameParser->finish();

        if ($this->incomingTransferDeliveryId !== null || $this->incomingTransferPayload !== '') {
            throw ReceiverLinkException::truncatedTransferPayload();
        }
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
            $detach = $this->detachCodec->decode($frame->payload);
            $this->lastRemoteDetachError = $detach->error;
            $this->state = ReceiverLinkState::Detached;

            return [ReceiverLinkEvent::LinkDetached];
        }

        if (str_starts_with($frame->payload, "\x00\x53\x14")) {
            $transferPayloadOffset = $this->transferPayloadOffset($frame->payload);
            $transfer = $this->transferCodec->decode(substr($frame->payload, 0, $transferPayloadOffset));
            $this->incomingTransferDeliveryId = $transfer->deliveryId;
            $this->incomingTransferPayload .= substr($frame->payload, $transferPayloadOffset);

            if ($transfer->more) {
                return [];
            }

            $this->receivedDeliveries[] = new ReceivedDelivery(
                deliveryId: $this->incomingTransferDeliveryId,
                message: $this->messageCodec->decode($this->incomingTransferPayload),
            );
            $this->unsettledDeliveries[$this->incomingTransferDeliveryId] = true;
            $this->incomingTransferDeliveryId = null;
            $this->incomingTransferPayload = '';

            return [ReceiverLinkEvent::MessageReceived];
        }

        throw ReceiverLinkException::unsupportedReceiverLinkPerformative();
    }

    private function transferPayloadOffset(string $payload): int
    {
        return 5 + ord($payload[4]);
    }

    /**
     * @return list<string>
     */
    private function settle(int $deliveryId, SettlementOutcome $outcome): array
    {
        if (!isset($this->unsettledDeliveries[$deliveryId])) {
            throw ReceiverLinkException::unknownDelivery();
        }

        unset($this->unsettledDeliveries[$deliveryId]);

        return [
            $this->frame($this->dispositionCodec->encode(new Disposition(
                deliveryId: $deliveryId,
                outcome: $outcome,
            ))),
        ];
    }
}
