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
        self::assertMatchesRegularExpression('/^test-long:\s+broker-reset$/m', $makefile);
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

        self::assertStringContainsString('$(BROKER_COMPOSE) up -d qpid artemis rabbitmq toxiproxy', $makefile);
        self::assertStringContainsString('$(BROKER_COMPOSE) exec -T rabbitmq rabbitmq-diagnostics -q ping', $makefile);
        self::assertStringContainsString('$(BROKER_COMPOSE) stop qpid artemis rabbitmq toxiproxy', $makefile);
        self::assertStringContainsString(
            '$(BROKER_COMPOSE) rm --force --volumes qpid artemis rabbitmq toxiproxy',
            $makefile,
        );
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

    private static function makefile(): string
    {
        $contents = file_get_contents(dirname(__DIR__, 2) . '/Makefile');

        if ($contents === false) {
            self::fail('Could not read project Makefile.');
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
