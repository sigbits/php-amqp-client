<?php

declare(strict_types=1);

namespace Sigbits\Amqp\Transport;

use Sigbits\Amqp\Protocol\Sasl\SaslClient;

final readonly class SaslStreamConnector
{
    public function __construct(
        private StreamConnector $streamConnector = new StreamConnector(),
        private SaslStreamAuthenticator $authenticator = new SaslStreamAuthenticator(),
    ) {
    }

    /**
     * @return resource
     */
    public function connect(ConnectionUri $uri, SaslClient $client, float $timeoutSeconds = 30.0): mixed
    {
        $stream = $this->streamConnector->connect($uri, $timeoutSeconds);
        $this->authenticator->authenticate($stream, $client);

        return $stream;
    }
}
