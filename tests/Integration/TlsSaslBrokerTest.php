<?php

declare(strict_types=1);

namespace Sigbits\Amqp\Tests\Integration;

use PHPUnit\Framework\TestCase;
use Sigbits\Amqp\Client\Connection;
use Sigbits\Amqp\Engine\ConnectionState;
use Sigbits\Amqp\Protocol\Sasl\SaslException;
use Sigbits\Amqp\Transport\TlsOptions;
use Sigbits\Amqp\Transport\TransportException;

final class TlsSaslBrokerTest extends TestCase
{
    public function testConnectsToArtemisOverTlsWithTrustedCertificateAndPlainSasl(): void
    {
        if (getenv('RUN_BROKER_SECURITY_TESTS') !== '1') {
            self::markTestSkipped('Set RUN_BROKER_SECURITY_TESTS=1 to run broker security integration tests.');
        }

        $connection = Connection::connect(
            self::artemisTlsUri(),
            timeoutSeconds: 5.0,
            tls: self::trustedArtemisTlsOptions(),
        );

        self::assertSame(ConnectionState::Opened, $connection->state());

        $connection->close();
    }

    public function testRejectsArtemisTlsConnectionWhenCertificateIsNotTrusted(): void
    {
        if (getenv('RUN_BROKER_SECURITY_TESTS') !== '1') {
            self::markTestSkipped('Set RUN_BROKER_SECURITY_TESTS=1 to run broker security integration tests.');
        }

        $this->expectException(TransportException::class);

        Connection::connect(
            self::artemisTlsUri(),
            timeoutSeconds: 5.0,
            tls: new TlsOptions(peerName: self::artemisTlsPeerName()),
        );
    }

    public function testRejectsInvalidPlainSaslCredentialsOverArtemisTls(): void
    {
        if (getenv('RUN_BROKER_SECURITY_TESTS') !== '1') {
            self::markTestSkipped('Set RUN_BROKER_SECURITY_TESTS=1 to run broker security integration tests.');
        }

        $this->expectException(SaslException::class);

        Connection::connect(
            self::artemisTlsUriWithInvalidPassword(),
            timeoutSeconds: 5.0,
            tls: self::trustedArtemisTlsOptions(),
        );
    }

    private static function artemisTlsUri(): string
    {
        return getenv('AMQP_ARTEMIS_TLS_URI') ?: 'amqps://guest:guest@artemis-tls:5671';
    }

    private static function artemisTlsUriWithInvalidPassword(): string
    {
        return getenv('AMQP_ARTEMIS_TLS_INVALID_URI') ?: 'amqps://guest:wrong@artemis-tls:5671';
    }

    private static function artemisTlsPeerName(): string
    {
        return getenv('AMQP_ARTEMIS_TLS_PEER_NAME') ?: 'artemis-tls.sigbits.test';
    }

    private static function trustedArtemisTlsOptions(): TlsOptions
    {
        return new TlsOptions(
            peerName: self::artemisTlsPeerName(),
            cafile: getenv('AMQP_ARTEMIS_TLS_CA_FILE') ?: '/app/docker/broker/tls/ca.crt',
        );
    }
}
