<?php

declare(strict_types=1);

namespace Sigbits\Amqp\Transport;

final readonly class TlsOptions
{
    public function __construct(
        public bool $verifyPeer = true,
        public bool $verifyPeerName = true,
        public ?string $peerName = null,
        public ?string $cafile = null,
        public ?string $localCert = null,
    ) {
    }

    /**
     * @return array{ssl: array<string, bool|string>}
     */
    public function streamContextOptions(string $defaultPeerName): array
    {
        $ssl = [
            'verify_peer' => $this->verifyPeer,
            'verify_peer_name' => $this->verifyPeerName,
            'peer_name' => $this->peerName ?? $defaultPeerName,
        ];

        if ($this->cafile !== null) {
            $ssl['cafile'] = $this->cafile;
        }

        if ($this->localCert !== null) {
            $ssl['local_cert'] = $this->localCert;
        }

        return ['ssl' => $ssl];
    }
}
