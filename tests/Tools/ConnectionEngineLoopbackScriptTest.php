<?php

declare(strict_types=1);

namespace Sigbits\Amqp\Tests\Tools;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Process\Process;

final class ConnectionEngineLoopbackScriptTest extends TestCase
{
    public function testConnectionEngineLoopbackProofScriptSucceeds(): void
    {
        $process = new Process([PHP_BINARY, 'tools/connection-engine-loopback.php']);
        $process->setWorkingDirectory(dirname(__DIR__, 2));
        $process->mustRun();

        self::assertSame(
            "client: Opened\nserver: Opened\nclient: Closed\nserver: Closed\n",
            $process->getOutput(),
        );
    }
}
