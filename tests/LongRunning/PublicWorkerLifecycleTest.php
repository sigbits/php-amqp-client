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

        for ($cycle = 0; $cycle < $cycles; ++$cycle) {
            $address = sprintf('sigbits.long.%s.%d.%s', $broker, $cycle, bin2hex(random_bytes(4)));

            if ($broker === 'qpid') {
                $this->createQpidQueue($address);
            }

            $connection = Connection::connect($uri, timeoutSeconds: 5.0);
            $session = $connection->beginSession();
            $sender = $session->openSender($address);
            $receiver = $session->openReceiver($address);

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
