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

    public function testConnectsToRabbitMqOverTlsWithTrustedCertificateAndPlainSasl(): void
    {
        if (getenv('RUN_BROKER_SECURITY_TESTS') !== '1') {
            self::markTestSkipped('Set RUN_BROKER_SECURITY_TESTS=1 to run broker security integration tests.');
        }

        $connection = Connection::connect(
            self::rabbitMqTlsUri(),
            timeoutSeconds: 5.0,
            tls: self::trustedRabbitMqTlsOptions(),
        );

        self::assertSame(ConnectionState::Opened, $connection->state());

        $connection->close();
    }

    public function testRejectsRabbitMqTlsConnectionWhenCertificateIsNotTrusted(): void
    {
        if (getenv('RUN_BROKER_SECURITY_TESTS') !== '1') {
            self::markTestSkipped('Set RUN_BROKER_SECURITY_TESTS=1 to run broker security integration tests.');
        }

        $this->expectException(TransportException::class);

        Connection::connect(
            self::rabbitMqTlsUri(),
            timeoutSeconds: 5.0,
            tls: new TlsOptions(peerName: self::rabbitMqTlsPeerName()),
        );
    }

    public function testRejectsInvalidPlainSaslCredentialsOverRabbitMqTls(): void
    {
        if (getenv('RUN_BROKER_SECURITY_TESTS') !== '1') {
            self::markTestSkipped('Set RUN_BROKER_SECURITY_TESTS=1 to run broker security integration tests.');
        }

        $this->expectException(SaslException::class);

        Connection::connect(
            self::rabbitMqTlsUriWithInvalidPassword(),
            timeoutSeconds: 5.0,
            tls: self::trustedRabbitMqTlsOptions(),
        );
    }

    public function testConnectsToQpidOverTlsWithTrustedCertificateAndPlainSasl(): void
    {
        if (getenv('RUN_BROKER_SECURITY_TESTS') !== '1') {
            self::markTestSkipped('Set RUN_BROKER_SECURITY_TESTS=1 to run broker security integration tests.');
        }

        $connection = Connection::connect(
            self::qpidTlsUri(),
            timeoutSeconds: 5.0,
            tls: self::trustedQpidTlsOptions(),
        );

        self::assertSame(ConnectionState::Opened, $connection->state());

        $connection->close();
    }

    public function testRejectsQpidTlsConnectionWhenCertificateIsNotTrusted(): void
    {
        if (getenv('RUN_BROKER_SECURITY_TESTS') !== '1') {
            self::markTestSkipped('Set RUN_BROKER_SECURITY_TESTS=1 to run broker security integration tests.');
        }

        $this->expectException(TransportException::class);

        Connection::connect(
            self::qpidTlsUri(),
            timeoutSeconds: 5.0,
            tls: new TlsOptions(peerName: self::qpidTlsPeerName()),
        );
    }

    public function testRejectsInvalidPlainSaslCredentialsOverQpidTls(): void
    {
        if (getenv('RUN_BROKER_SECURITY_TESTS') !== '1') {
            self::markTestSkipped('Set RUN_BROKER_SECURITY_TESTS=1 to run broker security integration tests.');
        }

        $this->expectException(SaslException::class);

        Connection::connect(
            self::qpidTlsUriWithInvalidPassword(),
            timeoutSeconds: 5.0,
            tls: self::trustedQpidTlsOptions(),
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

    private static function rabbitMqTlsUri(): string
    {
        return getenv('AMQP_RABBITMQ_TLS_URI') ?: 'amqps://guest:guest@rabbitmq-tls:5671';
    }

    private static function rabbitMqTlsUriWithInvalidPassword(): string
    {
        return getenv('AMQP_RABBITMQ_TLS_INVALID_URI') ?: 'amqps://guest:wrong@rabbitmq-tls:5671';
    }

    private static function rabbitMqTlsPeerName(): string
    {
        return getenv('AMQP_RABBITMQ_TLS_PEER_NAME') ?: 'rabbitmq-tls.sigbits.test';
    }

    private static function trustedRabbitMqTlsOptions(): TlsOptions
    {
        return new TlsOptions(
            peerName: self::rabbitMqTlsPeerName(),
            cafile: getenv('AMQP_RABBITMQ_TLS_CA_FILE') ?: '/app/docker/broker/tls/ca.crt',
        );
    }

    private static function qpidTlsUri(): string
    {
        return getenv('AMQP_QPID_TLS_URI') ?: 'amqps://guest:guest@qpid-tls:5671';
    }

    private static function qpidTlsUriWithInvalidPassword(): string
    {
        return getenv('AMQP_QPID_TLS_INVALID_URI') ?: 'amqps://guest:wrong@qpid-tls:5671';
    }

    private static function qpidTlsPeerName(): string
    {
        return getenv('AMQP_QPID_TLS_PEER_NAME') ?: 'qpid-tls.sigbits.test';
    }

    private static function trustedQpidTlsOptions(): TlsOptions
    {
        return new TlsOptions(
            peerName: self::qpidTlsPeerName(),
            cafile: getenv('AMQP_QPID_TLS_CA_FILE') ?: '/app/docker/broker/tls/qpid-tls.pem',
        );
    }
}
