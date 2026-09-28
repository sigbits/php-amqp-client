<?php

declare(strict_types=1);

namespace Sigbits\Amqp\Transport;

final readonly class ConnectionUri
{
    private const int DEFAULT_AMQP_PORT = 5672;
    private const int DEFAULT_AMQPS_PORT = 5671;

    private function __construct(
        public string $scheme,
        public string $host,
        public int $port,
        public ?string $username,
        public ?string $password,
        public string $path,
        private ?TlsOptions $tls,
    ) {
    }

    public static function parse(string $uri, ?TlsOptions $tls = null): self
    {
        $parts = parse_url($uri);

        if (!is_array($parts)) {
            throw TransportException::unsupportedScheme();
        }

        $scheme = $parts['scheme'] ?? null;

        if ($scheme !== 'amqp' && $scheme !== 'amqps') {
            throw TransportException::unsupportedScheme();
        }

        $host = $parts['host'] ?? null;

        if (!is_string($host) || $host === '') {
            throw TransportException::missingHost();
        }

        $usesTls = $scheme === 'amqps';

        return new self(
            scheme: $scheme,
            host: $host,
            port: $parts['port'] ?? ($usesTls ? self::DEFAULT_AMQPS_PORT : self::DEFAULT_AMQP_PORT),
            username: isset($parts['user']) ? rawurldecode((string) $parts['user']) : null,
            password: isset($parts['pass']) ? rawurldecode((string) $parts['pass']) : null,
            path: isset($parts['path']) ? (string) $parts['path'] : '',
            tls: $usesTls ? ($tls ?? new TlsOptions()) : $tls,
        );
    }

    public function usesTls(): bool
    {
        return $this->tls !== null;
    }

    /**
     * @return array{ssl: array<string, bool|string>}|array{}
     */
    public function streamContextOptions(): array
    {
        if ($this->tls === null) {
            return [];
        }

        return $this->tls->streamContextOptions($this->host);
    }
}
