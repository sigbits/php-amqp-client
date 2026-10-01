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

        self::assertStringContainsString('$(BROKER_COMPOSE) up -d qpid artemis artemis-tls rabbitmq rabbitmq-tls servicebus-sql servicebus-emulator toxiproxy', $makefile);
        self::assertStringContainsString('$(BROKER_COMPOSE) exec -T rabbitmq rabbitmq-diagnostics -q ping', $makefile);
        self::assertStringContainsString('$(BROKER_COMPOSE) stop qpid artemis artemis-tls rabbitmq rabbitmq-tls servicebus-emulator servicebus-sql toxiproxy', $makefile);
        self::assertStringContainsString(
            '$(BROKER_COMPOSE) rm --force --volumes qpid artemis artemis-tls rabbitmq rabbitmq-tls servicebus-emulator servicebus-sql toxiproxy',
            $makefile,
        );
    }

    public function testBrokerLifecycleIncludesAzureServiceBusEmulator(): void
    {
        $makefile = self::makefile();

        self::assertStringContainsString('servicebus-emulator', $makefile);
        self::assertStringContainsString('servicebus-sql', $makefile);
        self::assertStringContainsString('Azure Service Bus emulator ready.', $makefile);
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

    public function testBrokerComposeDefinesAzureServiceBusEmulator(): void
    {
        $compose = self::brokerCompose();
        $config = self::serviceBusEmulatorConfig();

        self::assertStringContainsString('servicebus-emulator:', $compose);
        self::assertStringContainsString('image: mcr.microsoft.com/azure-messaging/servicebus-emulator:latest', $compose);
        self::assertStringContainsString('SQL_SERVER: servicebus-sql', $compose);
        self::assertStringContainsString('MSSQL_SA_PASSWORD:', $compose);
        self::assertStringContainsString('ACCEPT_EULA: "Y"', $compose);
        self::assertStringContainsString('./docker/broker/servicebus-emulator-config.json:/ServiceBus_Emulator/ConfigFiles/Config.json:ro', $compose);
        self::assertStringContainsString('"56750:5672"', $compose);
        self::assertStringContainsString('"58300:5300"', $compose);
        self::assertStringContainsString('servicebus-sql:', $compose);
        self::assertStringContainsString('image: mcr.microsoft.com/mssql/server:2022-latest', $compose);
        self::assertStringContainsString('"Name": "sbemulatorns"', $config);
        self::assertStringContainsString('"Name": "sigbits.public.accept"', $config);
    }

    public function testBrokerCompatibilityDocumentsSecurityMatrix(): void
    {
        $documentation = self::brokerCompatibilityDocumentation();

        self::assertStringContainsString('ActiveMQ Artemis and RabbitMQ 4 through local TLS endpoints', $documentation);
        self::assertStringContainsString('Qpid Broker-J TLS/SASL security coverage is not yet wired', $documentation);
    }

    public function testBrokerCompatibilityDocumentsAzureServiceBusEmulatorMatrix(): void
    {
        $documentation = self::brokerCompatibilityDocumentation();
        $audit = self::v100ReleaseReadinessAudit();

        self::assertStringContainsString('Azure Service Bus emulator', $documentation);
        self::assertStringContainsString('AMQP TCP on `servicebus-emulator:5672`', $documentation);
        self::assertStringContainsString('does not claim production Azure Service Bus cloud coverage', $documentation);
        self::assertStringContainsString('Azure Service Bus emulator', $audit);
        self::assertStringContainsString('Ready with documented gaps.', $audit);
        self::assertStringContainsString('real Azure Service Bus cloud service remains an external-provider verification gap', $audit);
        self::assertStringNotContainsString('Confirm the supported broker matrix and document every accepted gap in the', $audit);
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
        self::assertStringContainsString('Connection::connect() no longer exposes SaslStreamConnector', $audit);
        self::assertStringContainsString('v1.0.0 production-oriented release work completed these API stability', $roadmap);
    }

    public function testV100ApiStabilityAuditDocumentsPublicSemantics(): void
    {
        $roadmap = self::roadmap();
        $audit = self::v100ApiStabilityAudit();

        self::assertStringContainsString('Committed User-Facing Semantics', $audit);
        self::assertStringContainsString('Message model commitment', $audit);
        self::assertStringContainsString('Timeout unit commitment', $audit);
        self::assertStringContainsString('Receiver credit commitment', $audit);
        self::assertStringContainsString('Exception retry boundaries', $audit);
        self::assertStringContainsString('Documented and locked the committed user-facing message model', $roadmap);
    }

    public function testRoadmapLinksV100ReleaseReadinessAudit(): void
    {
        $roadmap = self::roadmap();
        $audit = self::v100ReleaseReadinessAudit();

        self::assertStringContainsString('[v1.0.0 Release Readiness Audit](release-readiness-v1.0.0.md)', $roadmap);
        self::assertStringContainsString('v1.0.0 is ready to tag from the verified release candidate', $audit);
        self::assertStringContainsString('Committed Public API', $audit);
        self::assertStringContainsString('Production Hardening Gate', $audit);
        self::assertStringContainsString('Supported Broker Matrix', $audit);
        self::assertStringContainsString('User Documentation Gate', $audit);
        self::assertStringContainsString('Release Blockers', $audit);
    }

    public function testV100UserDocumentationGateHasGuideCoverage(): void
    {
        $readme = self::readme();
        $guide = self::userGuide();
        $audit = self::v100ReleaseReadinessAudit();

        self::assertStringContainsString('[User Guide](docs/user-guide.md)', $readme);
        self::assertStringContainsString('### Installation', $guide);
        self::assertStringContainsString('### Connection Configuration', $guide);
        self::assertStringContainsString('### Sending Messages', $guide);
        self::assertStringContainsString('### Receiving Messages', $guide);
        self::assertStringContainsString('### Delivery Settlement', $guide);
        self::assertStringContainsString('### Receiver Credit Management', $guide);
        self::assertStringContainsString('### TLS', $guide);
        self::assertStringContainsString('### SASL', $guide);
        self::assertStringContainsString('### Retry Boundaries', $guide);
        self::assertStringContainsString('### Broker Interoperability', $guide);
        self::assertStringContainsString('### Long-Running Worker Operation', $guide);
        self::assertStringContainsString('User Documentation Gate', $audit);
        self::assertStringContainsString('Ready.', $audit);
        self::assertStringNotContainsString('Complete the missing user-facing documentation gate.', $audit);
    }

    public function testDeveloperHowtoDocumentsContributorWorkflowAndTestTypes(): void
    {
        $readme = self::readme();
        $howto = self::developerHowto();
        $roadmap = self::roadmap();

        self::assertStringContainsString('[Developer HOWTO](docs/developer-howto.md)', $readme);
        self::assertStringContainsString('## Test Types', $howto);
        self::assertStringContainsString('### Broker Integration Tests', $howto);
        self::assertStringContainsString('### TLS/SASL Security Tests', $howto);
        self::assertStringContainsString('### Long-Running Worker Tests', $howto);
        self::assertStringContainsString('### Broker Restart Tests', $howto);
        self::assertStringContainsString('### Soak Profiles', $howto);
        self::assertStringContainsString('### Release Verification Matrix', $howto);
        self::assertStringContainsString('## Why Tests Are Skipped', $howto);
        self::assertStringContainsString('RUN_BROKER_TESTS=1', $howto);
        self::assertStringContainsString('RUN_BROKER_SECURITY_TESTS=1', $howto);
        self::assertStringContainsString('RUN_LONG_TESTS=1', $howto);
        self::assertStringContainsString('[Developer HOWTO](developer-howto.md)', $roadmap);
    }

    public function testRoadmapDocumentsPostV1DeliveryAndTransportDirection(): void
    {
        $roadmap = self::roadmap();

        self::assertStringContainsString('### v1.1: Developer Workflow and Receive Semantics', $roadmap);
        self::assertStringContainsString('### v1.2: Delivery Consumption Ergonomics', $roadmap);
        self::assertStringContainsString('### v1.5: Internal Transport Abstraction', $roadmap);
        self::assertStringContainsString('### v1.6: Additional Transports', $roadmap);
        self::assertStringContainsString('AMQP over WebSockets first if Azure Service Bus cloud coverage requires', $roadmap);
        self::assertStringContainsString('raw socket backend only if it proves concrete value over streams', $roadmap);
    }

    private static function readme(): string
    {
        $contents = file_get_contents(dirname(__DIR__, 2) . '/README.md');

        if ($contents === false) {
            self::fail('Could not read README documentation.');
        }

        return $contents;
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

    private static function v100ReleaseReadinessAudit(): string
    {
        $contents = file_get_contents(dirname(__DIR__, 2) . '/docs/release-readiness-v1.0.0.md');

        if ($contents === false) {
            self::fail('Could not read v1.0.0 release readiness audit.');
        }

        return $contents;
    }

    private static function userGuide(): string
    {
        $contents = file_get_contents(dirname(__DIR__, 2) . '/docs/user-guide.md');

        if ($contents === false) {
            self::fail('Could not read user guide documentation.');
        }

        return $contents;
    }

    private static function developerHowto(): string
    {
        $contents = file_get_contents(dirname(__DIR__, 2) . '/docs/developer-howto.md');

        if ($contents === false) {
            self::fail('Could not read developer HOWTO documentation.');
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

    private static function serviceBusEmulatorConfig(): string
    {
        $contents = file_get_contents(dirname(__DIR__, 2) . '/docker/broker/servicebus-emulator-config.json');

        if ($contents === false) {
            self::fail('Could not read Azure Service Bus emulator config.');
        }

        return $contents;
    }
}
