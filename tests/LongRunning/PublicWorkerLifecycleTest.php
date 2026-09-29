<?php

declare(strict_types=1);

namespace Sigbits\Amqp\Tests\LongRunning;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Sigbits\Amqp\Client\Connection;
use Sigbits\Amqp\Engine\ReceiverLinkState;
use Sigbits\Amqp\Engine\SenderLinkState;
use Sigbits\Amqp\Engine\SessionState;

final class PublicWorkerLifecycleTest extends TestCase
{
    #[DataProvider('brokerProvider')]
    public function testRepeatedPublicLifecycleCyclesDoNotLeakMemory(string $broker, string $uri): void
    {
        if (getenv('RUN_LONG_TESTS') !== '1') {
            self::markTestSkipped('Set RUN_LONG_TESTS=1 to run long-running worker hardening tests.');
        }

        $cycles = self::positiveIntegerFromEnvironment('AMQP_LONG_CYCLES', 100);
        $maxGrowthBytes = self::positiveIntegerFromEnvironment('AMQP_LONG_MAX_MEMORY_GROWTH_BYTES', 8 * 1024 * 1024);

        gc_collect_cycles();
        $startMemory = memory_get_usage(true);
        $runId = bin2hex(random_bytes(4));

        for ($cycle = 0; $cycle < $cycles; ++$cycle) {
            $address = sprintf('sigbits.long.%s.%s.%d', $broker, $runId, $cycle);

            if ($broker === 'qpid') {
                $this->createQpidQueue($address);
            }

            $connection = Connection::connect(
                $uri,
                containerId: sprintf('sigbits-long-lifecycle-%s-%s-%d', $broker, $runId, $cycle),
                timeoutSeconds: 5.0,
            );
            $session = $connection->beginSession();
            $sender = $session->openSender($address, name: sprintf('sender-%s-%d', $runId, $cycle));
            $receiver = $session->openReceiver($address, name: sprintf('receiver-%s-%d', $runId, $cycle));

            $sender->detach();
            $receiver->detach();

            self::assertSame(SenderLinkState::Detached, $sender->state());
            self::assertSame(ReceiverLinkState::Detached, $receiver->state());

            $session->end();
            self::assertSame(SessionState::Ended, $session->state());

            $connection->close();

            if ($cycle % 10 === 0) {
                gc_collect_cycles();
            }
        }

        gc_collect_cycles();
        $memoryGrowth = memory_get_usage(true) - $startMemory;

        self::assertLessThanOrEqual(
            $maxGrowthBytes,
            $memoryGrowth,
            sprintf('Memory grew by %d bytes across %d %s lifecycle cycles.', $memoryGrowth, $cycles, $broker),
        );
    }

    #[DataProvider('brokerProvider')]
    public function testRepeatedPublicSendReceiveCyclesDoNotLeakMemory(string $broker, string $uri): void
    {
        if (getenv('RUN_LONG_TESTS') !== '1') {
            self::markTestSkipped('Set RUN_LONG_TESTS=1 to run long-running worker hardening tests.');
        }

        $cycles = self::positiveIntegerFromEnvironment('AMQP_LONG_CYCLES', 100);
        $maxGrowthBytes = self::positiveIntegerFromEnvironment('AMQP_LONG_MAX_MEMORY_GROWTH_BYTES', 8 * 1024 * 1024);
        $runId = bin2hex(random_bytes(4));
        $address = sprintf('sigbits.long.flow.%s.%s', $broker, $runId);

        if ($broker === 'qpid') {
            $this->createQpidQueue($address);
        }

        gc_collect_cycles();
        $startMemory = memory_get_usage(true);

        if ($broker === 'artemis') {
            $connection = Connection::connect(
                $uri,
                containerId: sprintf('sigbits-long-flow-%s-%s', $broker, $runId),
                timeoutSeconds: 5.0,
            );
            $session = $connection->beginSession();
            $receiver = $session->openReceiver($address, name: sprintf('receiver-%s', $runId), credit: $cycles);
            $sender = $session->openSender($address, name: sprintf('sender-%s', $runId));

            for ($cycle = 0; $cycle < $cycles; ++$cycle) {
                $body = sprintf('long worker message %s %d', $broker, $cycle);

                $sender->send($body);
                $delivery = $receiver->receiveDelivery(timeoutMilliseconds: 5000);

                self::assertNotNull($delivery);
                self::assertSame($body, $delivery->message()->body);

                $delivery->accept();

                if ($cycle % 10 === 0) {
                    gc_collect_cycles();
                }
            }

            $session->end();
            $connection->close();
        } else {
            $producerConnection = Connection::connect(
                $uri,
                containerId: sprintf('sigbits-long-producer-%s-%s', $broker, $runId),
                timeoutSeconds: 5.0,
            );
            $producerSession = $producerConnection->beginSession();
            $sender = $producerSession->openSender($address, name: sprintf('sender-%s', $runId));

            for ($cycle = 0; $cycle < $cycles; ++$cycle) {
                $body = sprintf('long worker message %s %d', $broker, $cycle);

                $sender->send($body);
            }

            $producerSession->end();
            $producerConnection->close();

            $consumerConnection = Connection::connect(
                $uri,
                containerId: sprintf('sigbits-long-consumer-%s-%s', $broker, $runId),
                timeoutSeconds: 5.0,
            );
            $consumerSession = $consumerConnection->beginSession();
            $receiver = $consumerSession->openReceiver($address, name: sprintf('receiver-%s', $runId), credit: $cycles);

            for ($cycle = 0; $cycle < $cycles; ++$cycle) {
                $body = sprintf('long worker message %s %d', $broker, $cycle);
                $delivery = $receiver->receiveDelivery(timeoutMilliseconds: 5000);

                self::assertNotNull($delivery);
                self::assertSame($body, $delivery->message()->body);

                $delivery->accept();

                if ($cycle % 10 === 0) {
                    gc_collect_cycles();
                }
            }

            $consumerSession->end();
            $consumerConnection->close();
        }

        gc_collect_cycles();
        $memoryGrowth = memory_get_usage(true) - $startMemory;

        self::assertLessThanOrEqual(
            $maxGrowthBytes,
            $memoryGrowth,
            sprintf('Memory grew by %d bytes across %d %s send/receive cycles.', $memoryGrowth, $cycles, $broker),
        );
    }

    #[DataProvider('brokerProvider')]
    public function testRepeatedPublicReconnectMessageCyclesDoNotLeakMemory(string $broker, string $uri): void
    {
        if (getenv('RUN_LONG_TESTS') !== '1') {
            self::markTestSkipped('Set RUN_LONG_TESTS=1 to run long-running worker hardening tests.');
        }

        $cycles = self::positiveIntegerFromEnvironment('AMQP_LONG_RECONNECT_CYCLES', 25);
        $maxGrowthBytes = self::positiveIntegerFromEnvironment('AMQP_LONG_MAX_MEMORY_GROWTH_BYTES', 8 * 1024 * 1024);
        $runId = bin2hex(random_bytes(4));

        gc_collect_cycles();
        $startMemory = memory_get_usage(true);

        for ($cycle = 0; $cycle < $cycles; ++$cycle) {
            $address = sprintf('sigbits.long.reconnect.%s.%s.%d', $broker, $runId, $cycle);
            $body = sprintf('reconnect worker message %s %d', $broker, $cycle);

            if ($broker === 'qpid') {
                $this->createQpidQueue($address);
                $this->sendOneMessage(
                    uri: $uri,
                    address: $address,
                    body: $body,
                    containerId: sprintf('sigbits-long-reconnect-producer-%s-%s-%d', $broker, $runId, $cycle),
                    linkName: sprintf('sender-%s-%d', $runId, $cycle),
                );
                $this->receiveOneMessage(
                    uri: $uri,
                    address: $address,
                    expectedBody: $body,
                    containerId: sprintf('sigbits-long-reconnect-consumer-%s-%s-%d', $broker, $runId, $cycle),
                    linkName: sprintf('receiver-%s-%d', $runId, $cycle),
                );
            } else {
                $consumerConnection = Connection::connect(
                    $uri,
                    containerId: sprintf('sigbits-long-reconnect-consumer-%s-%s-%d', $broker, $runId, $cycle),
                    timeoutSeconds: 5.0,
                );
                $consumerSession = $consumerConnection->beginSession();
                $receiver = $consumerSession->openReceiver(
                    $address,
                    name: sprintf('receiver-%s-%d', $runId, $cycle),
                );

                $this->sendOneMessage(
                    uri: $uri,
                    address: $address,
                    body: $body,
                    containerId: sprintf('sigbits-long-reconnect-producer-%s-%s-%d', $broker, $runId, $cycle),
                    linkName: sprintf('sender-%s-%d', $runId, $cycle),
                );

                $delivery = $receiver->receiveDelivery(timeoutMilliseconds: 5000);

                self::assertNotNull($delivery);
                self::assertSame($body, $delivery->message()->body);

                $delivery->accept();
                $consumerSession->end();
                $consumerConnection->close();
            }

            if ($cycle % 10 === 0) {
                gc_collect_cycles();
            }
        }

        gc_collect_cycles();
        $memoryGrowth = memory_get_usage(true) - $startMemory;

        self::assertLessThanOrEqual(
            $maxGrowthBytes,
            $memoryGrowth,
            sprintf('Memory grew by %d bytes across %d %s reconnect message cycles.', $memoryGrowth, $cycles, $broker),
        );
    }

    #[DataProvider('brokerProvider')]
    public function testRepeatedPublicCreditWindowCyclesDoNotLeakMemory(string $broker, string $uri): void
    {
        if (getenv('RUN_LONG_TESTS') !== '1') {
            self::markTestSkipped('Set RUN_LONG_TESTS=1 to run long-running worker hardening tests.');
        }

        $cycles = self::positiveIntegerFromEnvironment('AMQP_LONG_CREDIT_CYCLES', 20);
        $creditWindow = self::positiveIntegerFromEnvironment('AMQP_LONG_CREDIT_WINDOW', 2);
        $maxGrowthBytes = self::positiveIntegerFromEnvironment('AMQP_LONG_MAX_MEMORY_GROWTH_BYTES', 8 * 1024 * 1024);
        $runId = bin2hex(random_bytes(4));
        $address = sprintf('sigbits.long.credit.%s.%s', $broker, $runId);

        if ($broker === 'qpid') {
            $this->createQpidQueue($address);
            $producerConnection = Connection::connect(
                $uri,
                containerId: sprintf('sigbits-long-credit-producer-%s-%s', $broker, $runId),
                timeoutSeconds: 5.0,
            );
            $producerSession = $producerConnection->beginSession();
            $sender = $producerSession->openSender($address, name: sprintf('sender-%s', $runId));

            for ($cycle = 0; $cycle < $cycles; ++$cycle) {
                $sender->send(sprintf('credit worker message %s %d', $broker, $cycle));
            }

            $producerSession->end();
            $producerConnection->close();
        }

        gc_collect_cycles();
        $startMemory = memory_get_usage(true);

        $consumerConnection = Connection::connect(
            $uri,
            containerId: sprintf('sigbits-long-credit-consumer-%s-%s', $broker, $runId),
            timeoutSeconds: 5.0,
        );
        $consumerSession = $consumerConnection->beginSession();
        $receiver = $consumerSession->openReceiver(
            $address,
            name: sprintf('receiver-%s', $runId),
            credit: min($creditWindow, $cycles),
        );

        if ($broker === 'artemis') {
            $sender = $consumerSession->openSender($address, name: sprintf('sender-%s', $runId));

            for ($cycle = 0; $cycle < $cycles; ++$cycle) {
                $sender->send(sprintf('credit worker message %s %d', $broker, $cycle));
            }
        }

        $messagesInCurrentWindow = 0;

        for ($cycle = 0; $cycle < $cycles; ++$cycle) {
            $body = sprintf('credit worker message %s %d', $broker, $cycle);
            $delivery = $receiver->receiveDelivery(timeoutMilliseconds: 5000);

            self::assertNotNull($delivery);
            self::assertSame($body, $delivery->message()->body);

            $delivery->accept();
            ++$messagesInCurrentWindow;

            $remaining = $cycles - $cycle - 1;

            if ($messagesInCurrentWindow === $creditWindow && $remaining > 0) {
                $receiver->grantCredit(min($creditWindow, $remaining));
                $messagesInCurrentWindow = 0;
                gc_collect_cycles();
            }
        }

        $consumerSession->end();
        $consumerConnection->close();

        gc_collect_cycles();
        $memoryGrowth = memory_get_usage(true) - $startMemory;

        self::assertLessThanOrEqual(
            $maxGrowthBytes,
            $memoryGrowth,
            sprintf('Memory grew by %d bytes across %d %s credit-window cycles.', $memoryGrowth, $cycles, $broker),
        );
    }

    #[DataProvider('brokerProvider')]
    public function testFragmentedLargePublicMessagesRoundTrip(string $broker, string $uri): void
    {
        if (getenv('RUN_LONG_TESTS') !== '1') {
            self::markTestSkipped('Set RUN_LONG_TESTS=1 to run long-running worker hardening tests.');
        }

        $cycles = self::positiveIntegerFromEnvironment('AMQP_LONG_LARGE_MESSAGE_CYCLES', 5);
        $bodyBytes = self::positiveIntegerFromEnvironment('AMQP_LONG_LARGE_MESSAGE_BYTES', 4096);
        $maxGrowthBytes = self::positiveIntegerFromEnvironment('AMQP_LONG_MAX_MEMORY_GROWTH_BYTES', 8 * 1024 * 1024);
        $runId = bin2hex(random_bytes(4));
        $address = sprintf('sigbits.long.large.%s.%s', $broker, $runId);

        if ($broker === 'qpid') {
            $this->createQpidQueue($address);
        }

        gc_collect_cycles();
        $startMemory = memory_get_usage(true);

        if ($broker === 'artemis') {
            $connection = Connection::connect(
                $uri,
                containerId: sprintf('sigbits-long-large-%s-%s', $broker, $runId),
                timeoutSeconds: 5.0,
            );
            $session = $connection->beginSession();
            $receiver = $session->openReceiver($address, name: sprintf('receiver-%s', $runId), credit: $cycles);
            $sender = $session->openSender($address, name: sprintf('sender-%s', $runId));

            for ($cycle = 0; $cycle < $cycles; ++$cycle) {
                $body = self::largeMessageBody($broker, $cycle, $bodyBytes);

                $sender->send($body, maxFrameSize: 256);
                $delivery = $receiver->receiveDelivery(timeoutMilliseconds: 5000);

                self::assertNotNull($delivery);
                self::assertSame($body, $delivery->message()->body);

                $delivery->accept();

                gc_collect_cycles();
            }

            $session->end();
            $connection->close();
        } else {
            $producerConnection = Connection::connect(
                $uri,
                containerId: sprintf('sigbits-long-large-producer-%s-%s', $broker, $runId),
                timeoutSeconds: 5.0,
            );
            $producerSession = $producerConnection->beginSession();
            $sender = $producerSession->openSender($address, name: sprintf('sender-%s', $runId));

            for ($cycle = 0; $cycle < $cycles; ++$cycle) {
                $sender->send(self::largeMessageBody($broker, $cycle, $bodyBytes), maxFrameSize: 256);
            }

            $producerSession->end();
            $producerConnection->close();

            $consumerConnection = Connection::connect(
                $uri,
                containerId: sprintf('sigbits-long-large-consumer-%s-%s', $broker, $runId),
                timeoutSeconds: 5.0,
            );
            $consumerSession = $consumerConnection->beginSession();
            $receiver = $consumerSession->openReceiver($address, name: sprintf('receiver-%s', $runId), credit: $cycles);

            for ($cycle = 0; $cycle < $cycles; ++$cycle) {
                $body = self::largeMessageBody($broker, $cycle, $bodyBytes);
                $delivery = $receiver->receiveDelivery(timeoutMilliseconds: 5000);

                self::assertNotNull($delivery);
                self::assertSame($body, $delivery->message()->body);

                $delivery->accept();

                gc_collect_cycles();
            }

            $consumerSession->end();
            $consumerConnection->close();
        }

        gc_collect_cycles();
        $memoryGrowth = memory_get_usage(true) - $startMemory;

        self::assertLessThanOrEqual(
            $maxGrowthBytes,
            $memoryGrowth,
            sprintf('Memory grew by %d bytes across %d %s large-message cycles.', $memoryGrowth, $cycles, $broker),
        );
    }

    /**
     * @return array<string, array{string, string}>
     */
    public static function brokerProvider(): array
    {
        return [
            'qpid' => ['qpid', getenv('AMQP_QPID_URI') ?: 'amqp://guest:guest@qpid:5672'],
            'artemis' => ['artemis', getenv('AMQP_ARTEMIS_URI') ?: 'amqp://guest:guest@artemis:5672'],
        ];
    }

    private static function positiveIntegerFromEnvironment(string $name, int $default): int
    {
        $value = getenv($name);

        if ($value === false) {
            return $default;
        }

        $integer = filter_var($value, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);

        if ($integer === false) {
            self::fail(sprintf('%s must be a positive integer.', $name));
        }

        return $integer;
    }

    private static function largeMessageBody(string $broker, int $cycle, int $bytes): string
    {
        $prefix = sprintf('large worker message %s %d ', $broker, $cycle);

        return $prefix . str_repeat('x', max(0, $bytes - strlen($prefix)));
    }

    private function sendOneMessage(
        string $uri,
        string $address,
        string $body,
        string $containerId,
        string $linkName,
    ): void {
        $connection = Connection::connect($uri, containerId: $containerId, timeoutSeconds: 5.0);
        $session = $connection->beginSession();
        $sender = $session->openSender($address, name: $linkName);

        $sender->send($body);

        $session->end();
        $connection->close();
    }

    private function receiveOneMessage(
        string $uri,
        string $address,
        string $expectedBody,
        string $containerId,
        string $linkName,
    ): void {
        $connection = Connection::connect($uri, containerId: $containerId, timeoutSeconds: 5.0);
        $session = $connection->beginSession();
        $receiver = $session->openReceiver($address, name: $linkName);
        $delivery = $receiver->receiveDelivery(timeoutMilliseconds: 5000);

        self::assertNotNull($delivery);
        self::assertSame($expectedBody, $delivery->message()->body);

        $delivery->accept();
        $session->end();
        $connection->close();
    }

    private function createQpidQueue(string $name): void
    {
        $body = json_encode([
            'type' => 'standard',
            'durable' => false,
        ]);

        if ($body === false) {
            self::fail('Could not encode Qpid queue creation payload.');
        }

        $context = stream_context_create([
            'http' => [
                'method' => 'PUT',
                'header' => 'Authorization: Basic ' . base64_encode('guest:guest') . "\r\n"
                    . "Content-Type: application/json\r\n",
                'content' => $body,
                'ignore_errors' => true,
            ],
        ]);
        $response = file_get_contents(
            'http://qpid:8080/api/latest/queue/default/default/' . rawurlencode($name),
            false,
            $context,
        );
        $status = $http_response_header[0] ?? '';

        if ($response === false || !str_contains($status, '201 Created')) {
            self::fail('Could not create Qpid test queue: ' . $status);
        }
    }
}
