<?php

declare(strict_types=1);

namespace Sigbits\Amqp\Protocol\Sasl;

final readonly class SaslClient
{
    private function __construct(
        private string $mechanism,
        private string $username = '',
        private string $password = '',
        private string $authorizationId = '',
    ) {
    }

    public static function anonymous(): self
    {
        return new self('ANONYMOUS');
    }

    public static function plain(string $username, string $password, string $authorizationId = ''): self
    {
        return new self(
            mechanism: 'PLAIN',
            username: $username,
            password: $password,
            authorizationId: $authorizationId,
        );
    }

    public function init(SaslMechanisms $mechanisms): SaslInit
    {
        if (!in_array($this->mechanism, $mechanisms->serverMechanisms, true)) {
            throw SaslException::mechanismNotOffered($this->mechanism);
        }

        return match ($this->mechanism) {
            'ANONYMOUS' => SaslInit::anonymous(),
            'PLAIN' => SaslInit::plain(
                username: $this->username,
                password: $this->password,
                authorizationId: $this->authorizationId,
            ),
            default => throw new \LogicException('Unsupported AMQP SASL mechanism.'),
        };
    }

    public function complete(SaslOutcome $outcome): void
    {
        if ($outcome->code !== SaslOutcomeCode::Ok) {
            throw SaslException::authenticationFailed($outcome->code);
        }
    }
}
