<?php

declare(strict_types=1);

namespace Sigbits\Amqp\Tests\Transport;

use PHPUnit\Framework\TestCase;
use Sigbits\Amqp\Transport\ConnectionUri;
use Sigbits\Amqp\Transport\TlsOptions;
use Sigbits\Amqp\Transport\TransportException;

final class ConnectionUriTest extends TestCase
{
    public function testParsesPlainAmqpUriWithDefaultPort(): void
    {
        $uri = ConnectionUri::parse('amqp://guest:secret@example.test/orders');

        self::assertSame('example.test', $uri->host);
        self::assertSame(5672, $uri->port);
        self::assertFalse($uri->usesTls());
        self::assertSame('guest', $uri->username);
        self::assertSame('secret', $uri->password);
        self::assertSame('/orders', $uri->path);
    }

    public function testParsesAmqpsUriWithTlsDefaultPortAndPeerName(): void
    {
        $uri = ConnectionUri::parse('amqps://broker.example.test');

        self::assertSame('broker.example.test', $uri->host);
        self::assertSame(5671, $uri->port);
        self::assertTrue($uri->usesTls());
        self::assertSame([
            'ssl' => [
                'verify_peer' => true,
                'verify_peer_name' => true,
                'peer_name' => 'broker.example.test',
            ],
        ], $uri->streamContextOptions());
    }

    public function testExplicitTlsOptionsOverridePeerNameAndCertificateAuthority(): void
    {
        $uri = ConnectionUri::parse(
            'amqps://broker.example.test:5679',
            tls: new TlsOptions(
                peerName: 'amqp.internal',
                cafile: '/etc/ssl/private-ca.pem',
            ),
        );

        self::assertSame(5679, $uri->port);
        self::assertSame([
            'ssl' => [
                'verify_peer' => true,
                'verify_peer_name' => true,
                'peer_name' => 'amqp.internal',
                'cafile' => '/etc/ssl/private-ca.pem',
            ],
        ], $uri->streamContextOptions());
    }

    public function testRejectsUnsupportedScheme(): void
    {
        $this->expectException(TransportException::class);
        $this->expectExceptionMessage('Unsupported AMQP connection URI scheme.');

        ConnectionUri::parse('http://example.test');
    }

    public function testRejectsUriWithoutHost(): void
    {
        $this->expectException(TransportException::class);
        $this->expectExceptionMessage('AMQP connection URI requires a host.');

        ConnectionUri::parse('amqps:queue');
    }
}
