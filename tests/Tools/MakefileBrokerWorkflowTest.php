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

    private static function makefile(): string
    {
        $contents = file_get_contents(dirname(__DIR__, 2) . '/Makefile');

        if ($contents === false) {
            self::fail('Could not read project Makefile.');
        }

        return $contents;
    }
}
