<?php

declare(strict_types=1);

namespace Sigbits\Amqp\Tests\Tools;

use PHPUnit\Framework\TestCase;

final class MakefileBrokerWorkflowTest extends TestCase
{
    public function testBrokerSuitesResetContainersBeforeRunning(): void
    {
        $makefile = self::makefile();

        self::assertMatchesRegularExpression('/^test-integration:\s+broker-reset$/m', $makefile);
        self::assertMatchesRegularExpression('/^test-security:\s+broker-reset$/m', $makefile);
        self::assertMatchesRegularExpression('/^test-long:\s+broker-reset$/m', $makefile);
        self::assertMatchesRegularExpression('/^test-broker-restart:\s+broker-reset$/m', $makefile);
    }

    public function testBrokerResetRecreatesBrokerStack(): void
    {
        $makefile = self::makefile();

        self::assertMatchesRegularExpression('/^\.PHONY:.*\bbroker-reset\b/m', $makefile);
        self::assertMatchesRegularExpression(
            '/^broker-reset:\n\t@\$\(MAKE\) broker-down\n\t@\$\(MAKE\) broker-up$/m',
            $makefile,
        );
    }

    public function testBrokerLifecycleIncludesRabbitMq(): void
    {
        $makefile = self::makefile();

        self::assertStringContainsString('$(BROKER_COMPOSE) up -d qpid artemis artemis-tls rabbitmq rabbitmq-tls toxiproxy', $makefile);
        self::assertStringContainsString('$(BROKER_COMPOSE) exec -T rabbitmq rabbitmq-diagnostics -q ping', $makefile);
        self::assertStringContainsString('$(BROKER_COMPOSE) stop qpid artemis artemis-tls rabbitmq rabbitmq-tls toxiproxy', $makefile);
        self::assertStringContainsString(
            '$(BROKER_COMPOSE) rm --force --volumes qpid artemis artemis-tls rabbitmq rabbitmq-tls toxiproxy',
            $makefile,
        );
    }

    public function testBrokerRestartSuiteUsesHostOrchestrator(): void
    {
        $makefile = self::makefile();

        self::assertStringContainsString(
            './tools/broker-restart.sh testPublicReceiverObservesBrokerRestartAgainstArtemis',
            $makefile,
        );
        self::assertStringContainsString(
            './tools/broker-restart.sh testPublicSenderObservesBrokerRestartAgainstArtemis',
            $makefile,
        );
        self::assertStringContainsString(
            './tools/broker-restart.sh testPublicSettlementObservesBrokerRestartAgainstArtemis',
            $makefile,
        );
    }

    public function testLongRunningSoakProfilesUseDedicatedRunner(): void
    {
        $makefile = self::makefile();

        self::assertMatchesRegularExpression('/^SOAK_PROFILE \?= all$/m', $makefile);
        self::assertMatchesRegularExpression('/^test-soak:\s+broker-reset$/m', $makefile);
        self::assertStringContainsString('./tools/long-profile.sh $(SOAK_PROFILE)', $makefile);
    }

    public function testLongRunningSoakProfileRunnerDefinesSupportedProfiles(): void
    {
        $script = self::longProfileScript();

        foreach (['send-only', 'receive-only', 'request-reply', 'bounded-credit', 'reconnect', 'large-message'] as $profile) {
            self::assertStringContainsString($profile, $script);
        }

        self::assertStringContainsString('AMQP_LONG_PROFILE', $script);
        self::assertStringContainsString('composer test:long', $script);
    }

    public function testSecuritySuiteUsesTlsBrokerEndpoint(): void
    {
        $makefile = self::makefile();

        self::assertStringContainsString('RUN_BROKER_SECURITY_TESTS=1', $makefile);
        self::assertStringContainsString('AMQP_ARTEMIS_TLS_URI=amqps://guest:guest@artemis-tls:5671', $makefile);
        self::assertStringContainsString('AMQP_ARTEMIS_TLS_CA_FILE=/app/docker/broker/tls/ca.crt', $makefile);
        self::assertStringContainsString('AMQP_ARTEMIS_TLS_PEER_NAME=artemis-tls.sigbits.test', $makefile);
        self::assertStringContainsString('AMQP_RABBITMQ_TLS_URI=amqps://guest:guest@rabbitmq-tls:5671', $makefile);
        self::assertStringContainsString('AMQP_RABBITMQ_TLS_CA_FILE=/app/docker/broker/tls/ca.crt', $makefile);
        self::assertStringContainsString('AMQP_RABBITMQ_TLS_PEER_NAME=rabbitmq-tls.sigbits.test', $makefile);
        self::assertStringContainsString('composer test:integration -- --filter TlsSaslBrokerTest', $makefile);
    }

    public function testBrokerComposeDefinesArtemisTlsEndpoint(): void
    {
        $compose = self::brokerCompose();

        self::assertStringContainsString('artemis-tls:', $compose);
        self::assertStringContainsString('image: haproxy:', $compose);
        self::assertStringContainsString('"56731:5671"', $compose);
        self::assertStringContainsString('./docker/broker/haproxy-artemis-tls.cfg:/usr/local/etc/haproxy/haproxy.cfg:ro', $compose);
        self::assertStringContainsString('./docker/broker/tls:/usr/local/etc/haproxy/certs:ro', $compose);
    }

    public function testBrokerComposeDefinesRabbitMqTlsEndpoint(): void
    {
        $compose = self::brokerCompose();

        self::assertStringContainsString('rabbitmq-tls:', $compose);
        self::assertStringContainsString('image: haproxy:', $compose);
        self::assertStringContainsString('"56741:5671"', $compose);
        self::assertStringContainsString('./docker/broker/haproxy-rabbitmq-tls.cfg:/usr/local/etc/haproxy/haproxy.cfg:ro', $compose);
        self::assertStringContainsString('./docker/broker/tls:/usr/local/etc/haproxy/certs:ro', $compose);
    }

    public function testBrokerComposeDefinesRabbitMqAmqp10Service(): void
    {
        $compose = self::brokerCompose();

        self::assertStringContainsString('rabbitmq:', $compose);
        self::assertStringContainsString('image: rabbitmq:4-management', $compose);
        self::assertStringContainsString('RABBITMQ_DEFAULT_USER: guest', $compose);
        self::assertStringContainsString('RABBITMQ_DEFAULT_PASS: guest', $compose);
        self::assertStringContainsString('"56740:5672"', $compose);
        self::assertStringContainsString('"58172:15672"', $compose);
    }

    public function testBrokerCompatibilityDocumentsSecurityMatrix(): void
    {
        $documentation = self::brokerCompatibilityDocumentation();

        self::assertStringContainsString('ActiveMQ Artemis and RabbitMQ 4 through local TLS endpoints', $documentation);
        self::assertStringContainsString('Qpid Broker-J TLS/SASL security coverage is not yet wired', $documentation);
    }

    public function testRoadmapLinksV090ReleaseReadinessAudit(): void
    {
        $roadmap = self::roadmap();
        $audit = self::v090ReleaseReadinessAudit();

        self::assertStringContainsString('[v0.9.0 Release Readiness Audit](release-readiness-v0.9.0.md)', $roadmap);
        self::assertStringContainsString('Qpid Broker-J TLS/SASL security coverage remains a documented local-matrix', $audit);
        self::assertStringContainsString('v0.9.0 is ready to tag', $audit);
    }

    public function testRoadmapLinksV100ApiStabilityAudit(): void
    {
        $roadmap = self::roadmap();
        $audit = self::v100ApiStabilityAudit();

        self::assertStringContainsString('[v1.0.0 Public API Stability Audit](api-stability-v1.0.0.md)', $roadmap);
        self::assertStringContainsString('Session, Sender, Receiver, and Delivery constructors are hidden from the supported public API', $audit);
        self::assertStringContainsString('Begin v1.0.0 production-oriented release work', $roadmap);
    }

    private static function makefile(): string
    {
        $contents = file_get_contents(dirname(__DIR__, 2) . '/Makefile');

        if ($contents === false) {
            self::fail('Could not read project Makefile.');
        }

        return $contents;
    }

    private static function brokerCompatibilityDocumentation(): string
    {
        $contents = file_get_contents(dirname(__DIR__, 2) . '/docs/broker-compatibility.md');

        if ($contents === false) {
            self::fail('Could not read broker compatibility documentation.');
        }

        return $contents;
    }

    private static function roadmap(): string
    {
        $contents = file_get_contents(dirname(__DIR__, 2) . '/docs/roadmap.md');

        if ($contents === false) {
            self::fail('Could not read roadmap documentation.');
        }

        return $contents;
    }

    private static function v090ReleaseReadinessAudit(): string
    {
        $contents = file_get_contents(dirname(__DIR__, 2) . '/docs/release-readiness-v0.9.0.md');

        if ($contents === false) {
            self::fail('Could not read v0.9.0 release readiness audit.');
        }

        return $contents;
    }

    private static function v100ApiStabilityAudit(): string
    {
        $contents = file_get_contents(dirname(__DIR__, 2) . '/docs/api-stability-v1.0.0.md');

        if ($contents === false) {
            self::fail('Could not read v1.0.0 public API stability audit.');
        }

        return $contents;
    }

    private static function longProfileScript(): string
    {
        $contents = file_get_contents(dirname(__DIR__, 2) . '/tools/long-profile.sh');

        if ($contents === false) {
            self::fail('Could not read long-running profile script.');
        }

        return $contents;
    }

    private static function brokerCompose(): string
    {
        $contents = file_get_contents(dirname(__DIR__, 2) . '/docker-compose.broker.yml');

        if ($contents === false) {
            self::fail('Could not read broker Docker Compose file.');
        }

        return $contents;
    }
}
