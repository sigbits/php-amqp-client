<?php

declare(strict_types=1);

namespace Sigbits\Amqp\Protocol\Sasl;

use RuntimeException;

final class SaslException extends RuntimeException
{
    public static function expectedInitDescriptor(): self
    {
        return new self('Expected AMQP SASL init performative descriptor.');
    }

    public static function missingInitMechanism(): self
    {
        return new self('AMQP SASL init performative requires mechanism.');
    }

    public static function truncatedInit(): self
    {
        return new self('Truncated AMQP SASL init performative.');
    }

    public static function expectedMechanismsDescriptor(): self
    {
        return new self('Expected AMQP SASL mechanisms performative descriptor.');
    }

    public static function missingServerMechanisms(): self
    {
        return new self('AMQP SASL mechanisms performative requires server mechanisms.');
    }

    public static function truncatedMechanisms(): self
    {
        return new self('Truncated AMQP SASL mechanisms performative.');
    }

    public static function expectedOutcomeDescriptor(): self
    {
        return new self('Expected AMQP SASL outcome performative descriptor.');
    }

    public static function missingOutcomeCode(): self
    {
        return new self('AMQP SASL outcome performative requires code.');
    }

    public static function truncatedOutcome(): self
    {
        return new self('Truncated AMQP SASL outcome performative.');
    }

    public static function mechanismNotOffered(string $mechanism): self
    {
        return new self(sprintf('AMQP SASL server does not offer %s mechanism.', $mechanism));
    }

    public static function authenticationFailed(SaslOutcomeCode $code): self
    {
        $codeName = match ($code) {
            SaslOutcomeCode::Ok => 'OK',
            SaslOutcomeCode::Auth => 'AUTH',
            SaslOutcomeCode::Sys => 'SYS',
            SaslOutcomeCode::SysPerm => 'SYS-PERM',
            SaslOutcomeCode::SysTemp => 'SYS-TEMP',
        };

        return new self(sprintf('AMQP SASL authentication failed with %s outcome.', $codeName));
    }
}
