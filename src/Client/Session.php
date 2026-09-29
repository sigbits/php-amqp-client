<?php

declare(strict_types=1);

namespace Sigbits\Amqp\Client;

use Closure;
use Sigbits\Amqp\Engine\SessionEngine;
use Sigbits\Amqp\Engine\SessionState;

final class Session
{
    /**
     * @param callable(list<string>): void $writeAll
     * @param callable(): string $readFrame
     */
    public function __construct(
        private readonly SessionEngine $engine,
        callable $writeAll,
        callable $readFrame,
    ) {
        $this->writeAll = $writeAll(...);
        $this->readFrame = $readFrame(...);
    }

    /**
     * @var Closure(list<string>): void
     */
    private readonly Closure $writeAll;

    /**
     * @var Closure(): string
     */
    private readonly Closure $readFrame;

    public function state(): SessionState
    {
        return $this->engine->state();
    }

    public function end(): void
    {
        ($this->writeAll)($this->engine->end());

        while ($this->engine->state() !== SessionState::Ended) {
            $this->engine->push(($this->readFrame)());
        }
    }
}
