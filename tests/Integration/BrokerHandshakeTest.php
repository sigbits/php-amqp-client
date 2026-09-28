<?php

declare(strict_types=1);

namespace Sigbits\Amqp\Tests\Integration;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Sigbits\Amqp\Engine\ConnectionEngine;
use Sigbits\Amqp\Engine\ConnectionEvent;
use Sigbits\Amqp\Engine\ConnectionState;
use Sigbits\Amqp\Protocol\Sasl\SaslClient;
use Sigbits\Amqp\Transport\ConnectionUri;
use Sigbits\Amqp\Transport\SaslStreamConnector;
use Sigbits\Amqp\Transport\TransportException;

final class BrokerHandshakeTest extends TestCase
{
    #[DataProvider('brokerProvider')]
    public function testOpensAmqpConnectionAgainstBroker(string $uri, SaslClient $saslClient): void
    {
        if (getenv('RUN_BROKER_TESTS') !== '1') {
            self::markTestSkipped('Set RUN_BROKER_TESTS=1 to run broker integration tests.');
        }

        $stream = $this->connectWhenBrokerIsReady($uri, $saslClient);
        stream_set_timeout($stream, 5);

        $engine = new ConnectionEngine(localContainerId: 'sigbits-php-amqp-client-test');

        foreach ($engine->start() as $outgoing) {
            $this->writeFully($stream, $outgoing);
        }

        while ($engine->state() !== ConnectionState::Opened) {
            $events = $engine->push($this->readChunk($stream));

            if (in_array(ConnectionEvent::ConnectionOpened, $events, true)) {
                break;
            }
        }

        self::assertSame(ConnectionState::Opened, $engine->state());
    }

    /**
     * @return array<string, array{string, SaslClient}>
     */
    public static function brokerProvider(): array
    {
        return [
            'qpid' => [
                getenv('AMQP_QPID_URI') ?: 'amqp://qpid:5672',
                SaslClient::plain(
                    getenv('AMQP_QPID_USER') ?: 'guest',
                    getenv('AMQP_QPID_PASSWORD') ?: 'guest',
                ),
            ],
            'artemis' => [
                getenv('AMQP_ARTEMIS_URI') ?: 'amqp://artemis:5672',
                SaslClient::plain(
                    getenv('AMQP_ARTEMIS_USER') ?: 'guest',
                    getenv('AMQP_ARTEMIS_PASSWORD') ?: 'guest',
                ),
            ],
        ];
    }

    /**
     * @return resource
     */
    private function connectWhenBrokerIsReady(string $uri, SaslClient $saslClient): mixed
    {
        $connector = new SaslStreamConnector();
        $deadline = microtime(true) + 30.0;
        $lastException = null;

        do {
            try {
                return $connector->connect(
                    ConnectionUri::parse($uri),
                    $saslClient,
                    timeoutSeconds: 1.0,
                );
            } catch (TransportException $exception) {
                $lastException = $exception;
                usleep(250_000);
            }
        } while (microtime(true) < $deadline);

        self::fail(sprintf(
            'Broker at %s did not become ready for AMQP/SASL handshake: %s',
            $uri,
            $lastException->getMessage(),
        ));
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
                self::fail('Failed to write AMQP handshake bytes to broker.');
            }

            $written += $chunkLength;
        }
    }

    /**
     * @param resource $stream
     */
    private function readChunk(mixed $stream): string
    {
        $chunk = fread($stream, 8192);

        if ($chunk === false || $chunk === '') {
            self::fail('Broker closed the AMQP stream before opening the connection.');
        }

        $metadata = stream_get_meta_data($stream);

        if ($metadata['timed_out'] === true) {
            self::fail('Timed out waiting for broker AMQP open frame.');
        }

        return $chunk;
    }
}
