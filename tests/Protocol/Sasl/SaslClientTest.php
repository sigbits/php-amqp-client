<?php

declare(strict_types=1);

namespace Sigbits\Amqp\Tests\Protocol\Sasl;

use PHPUnit\Framework\TestCase;
use Sigbits\Amqp\Protocol\Sasl\SaslClient;
use Sigbits\Amqp\Protocol\Sasl\SaslException;
use Sigbits\Amqp\Protocol\Sasl\SaslInit;
use Sigbits\Amqp\Protocol\Sasl\SaslMechanisms;
use Sigbits\Amqp\Protocol\Sasl\SaslOutcome;
use Sigbits\Amqp\Protocol\Sasl\SaslOutcomeCode;

final class SaslClientTest extends TestCase
{
    public function testAnonymousClientStartsWithAnonymousInitWhenOffered(): void
    {
        $client = SaslClient::anonymous();

        self::assertEquals(
            SaslInit::anonymous(),
            $client->init(new SaslMechanisms(['ANONYMOUS', 'PLAIN'])),
        );
    }

    public function testPlainClientStartsWithPlainInitWhenOffered(): void
    {
        $client = SaslClient::plain(username: 'user', password: 'pass');

        self::assertEquals(
            SaslInit::plain(username: 'user', password: 'pass'),
            $client->init(new SaslMechanisms(['ANONYMOUS', 'PLAIN'])),
        );
    }

    public function testPlainClientRejectsServerWithoutPlainMechanism(): void
    {
        $client = SaslClient::plain(username: 'user', password: 'pass');

        $this->expectException(SaslException::class);
        $this->expectExceptionMessage('AMQP SASL server does not offer PLAIN mechanism.');

        $client->init(new SaslMechanisms(['ANONYMOUS']));
    }

    public function testCompletesOnOkOutcome(): void
    {
        $client = SaslClient::anonymous();

        $client->complete(SaslOutcome::ok());

        self::addToAssertionCount(1);
    }

    public function testRejectsFailedOutcome(): void
    {
        $client = SaslClient::anonymous();

        $this->expectException(SaslException::class);
        $this->expectExceptionMessage('AMQP SASL authentication failed with AUTH outcome.');

        $client->complete(new SaslOutcome(SaslOutcomeCode::Auth));
    }
}
