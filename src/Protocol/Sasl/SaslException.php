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
}
