<?php

declare(strict_types=1);

namespace Sigbits\Amqp\Tests\Integration;

use PHPUnit\Framework\TestCase;
use Sigbits\Amqp\Engine\ConnectionEngine;
use Sigbits\Amqp\Engine\ConnectionState;
use Sigbits\Amqp\Engine\ReceiverLinkEngine;
use Sigbits\Amqp\Engine\ReceiverLinkEvent;
use Sigbits\Amqp\Engine\ReceiverLinkState;
use Sigbits\Amqp\Engine\SenderLinkEngine;
use Sigbits\Amqp\Engine\SenderLinkState;
use Sigbits\Amqp\Engine\SessionEngine;
use Sigbits\Amqp\Engine\SessionState;
use Sigbits\Amqp\Protocol\Message\Message;
use Sigbits\Amqp\Protocol\Sasl\SaslClient;
use Sigbits\Amqp\Transport\ConnectionUri;
use Sigbits\Amqp\Transport\SaslStreamConnector;

final class ArtemisMessageRoundTripTest extends TestCase
{
    public function testRoundTripsDataMessageThroughArtemis(): void
    {
        if (getenv('RUN_BROKER_TESTS') !== '1') {
            self::markTestSkipped('Set RUN_BROKER_TESTS=1 to run broker integration tests.');
        }

        $stream = (new SaslStreamConnector())->connect(
            ConnectionUri::parse(getenv('AMQP_ARTEMIS_URI') ?: 'amqp://artemis:5672'),
            SaslClient::plain(
                getenv('AMQP_ARTEMIS_USER') ?: 'guest',
                getenv('AMQP_ARTEMIS_PASSWORD') ?: 'guest',
            ),
            timeoutSeconds: 5.0,
        );
        stream_set_timeout($stream, 5);

        $this->openConnection($stream);
        $this->beginSession($stream);

        $address = 'sigbits.roundtrip.' . bin2hex(random_bytes(4));
        $receiver = new ReceiverLinkEngine(
            sessionChannel: 0,
            name: 'receiver',
            handle: 1,
            sourceAddress: $address,
        );
        $sender = new SenderLinkEngine(
            sessionChannel: 0,
            name: 'sender',
            handle: 0,
            targetAddress: $address,
        );

        $this->writeAll($stream, $receiver->attach());
        $this->waitForReceiverAttach($stream, $receiver);
        $this->writeAll($stream, $receiver->grantCredit(deliveryCount: 0, linkCredit: 1));

        $this->writeAll($stream, $sender->attach());
        $this->waitForSenderCredit($stream, $sender);

        $this->writeAll($stream, $sender->transfer(new Message(body: 'hello broker')));

        self::assertSame('hello broker', $this->waitForMessage($stream, $receiver)->body);
    }

    /**
     * @param resource $stream
     */
    private function openConnection(mixed $stream): void
    {
        $engine = new ConnectionEngine(localContainerId: 'sigbits-php-amqp-client-roundtrip-test');

        $this->writeAll($stream, $engine->start());
        $engine->push($this->readExactly($stream, 8) . $this->readFrame($stream)[1]);

        self::assertSame(ConnectionState::Opened, $engine->state());
    }

    /**
     * @param resource $stream
     */
    private function beginSession(mixed $stream): void
    {
        $engine = new SessionEngine(localChannel: 0);

        $this->writeAll($stream, $engine->begin());
        $engine->push($this->readFrame($stream)[1]);

        self::assertSame(SessionState::Mapped, $engine->state());
    }

    /**
     * @param resource $stream
     */
    private function waitForReceiverAttach(mixed $stream, ReceiverLinkEngine $receiver): void
    {
        while ($receiver->state() !== ReceiverLinkState::Attached) {
            [$payload, $frame] = $this->readFrame($stream);
            $this->failIfClose($payload);

            if (str_starts_with($payload, "\x00\x53\x12")) {
                $receiver->push($frame);
            }
        }
    }

    /**
     * @param resource $stream
     */
    private function waitForSenderCredit(mixed $stream, SenderLinkEngine $sender): void
    {
        while ($sender->state() !== SenderLinkState::Attached || $sender->availableCredit() === 0) {
            [$payload, $frame] = $this->readFrame($stream);
            $this->failIfClose($payload);

            if (str_starts_with($payload, "\x00\x53\x12") || str_starts_with($payload, "\x00\x53\x13")) {
                $sender->push($frame);
            }
        }
    }

    /**
     * @param resource $stream
     */
    private function waitForMessage(mixed $stream, ReceiverLinkEngine $receiver): Message
    {
        $deadline = microtime(true) + 5.0;

        do {
            $message = $receiver->receive();

            if ($message !== null) {
                return $message;
            }

            [$payload, $frame] = $this->readFrame($stream);
            $this->failIfClose($payload);

            if (str_starts_with($payload, "\x00\x53\x14")) {
                $events = $receiver->push($frame);

                if (in_array(ReceiverLinkEvent::MessageReceived, $events, true)) {
                    $message = $receiver->receive();

                    if ($message !== null) {
                        return $message;
                    }
                }
            }
        } while (microtime(true) < $deadline);

        self::fail('Timed out waiting for Artemis to deliver the round-trip AMQP message.');
    }

    /**
     * @param resource $stream
     * @param list<string> $frames
     */
    private function writeAll(mixed $stream, array $frames): void
    {
        foreach ($frames as $frame) {
            $this->writeFully($stream, $frame);
        }
    }

    /**
     * @param resource $stream
     */
    private function writeFully(mixed $stream, string $bytes): void
    {
        $written = 0;

        while ($written < strlen($bytes)) {
            $chunkLength = fwrite($stream, substr($bytes, $written));

            if ($chunkLength === false || $chunkLength === 0) {
                self::fail('Failed to write AMQP bytes to broker.');
            }

            $written += $chunkLength;
        }
    }

    /**
     * @param resource $stream
     *
     * @return array{string, string}
     */
    private function readFrame(mixed $stream): array
    {
        $header = $this->readExactly($stream, 8);
        $frameSize = (ord($header[0]) << 24)
            | (ord($header[1]) << 16)
            | (ord($header[2]) << 8)
            | ord($header[3]);
        $payload = $this->readExactly($stream, $frameSize - 8);

        return [$payload, $header . $payload];
    }

    /**
     * @param resource $stream
     */
    private function readExactly(mixed $stream, int $length): string
    {
        $bytes = '';

        while (strlen($bytes) < $length) {
            $chunk = fread($stream, $length - strlen($bytes));

            if ($chunk === false || $chunk === '') {
                $metadata = stream_get_meta_data($stream);

                if ($metadata['timed_out'] === true) {
                    self::fail('Timed out waiting for AMQP broker bytes.');
                }

                self::fail('Broker closed the AMQP stream unexpectedly.');
            }

            $bytes .= $chunk;
        }

        return $bytes;
    }

    private function failIfClose(string $payload): void
    {
        if (str_starts_with($payload, "\x00\x53\x18")) {
            self::fail('Broker closed the AMQP connection: ' . bin2hex($payload));
        }
    }
}
