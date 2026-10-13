<?php

declare(strict_types=1);

namespace Sigbits\Amqp\Tests\Protocol\Message;

use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use Sigbits\Amqp\Protocol\Message\Message;
use Sigbits\Amqp\Protocol\Message\MessageBodySection;

final class MessageTest extends TestCase
{
    public function testLegacyDataBodyInitializesSingleDataSection(): void
    {
        $message = new Message(body: 'hello');

        self::assertSame('hello', $message->body);
        self::assertSame(['hello'], $message->dataSections);
        self::assertSame([], $message->bodySequences);
    }

    public function testExplicitDataSectionsDefineCanonicalDataBody(): void
    {
        $message = new Message(
            body: 'legacy',
            dataSections: ['one', 'two'],
        );

        self::assertSame('one', $message->body);
        self::assertSame(['one', 'two'], $message->dataSections);
        self::assertSame([], $message->bodySequences);
    }

    public function testLegacyAmqpSequenceBodyInitializesSingleSequenceSection(): void
    {
        $message = new Message(
            body: '',
            bodySection: MessageBodySection::AmqpSequence,
            bodySequence: ['one', 'two'],
        );

        self::assertSame(['one', 'two'], $message->bodySequence);
        self::assertSame([['one', 'two']], $message->bodySequences);
        self::assertSame([], $message->dataSections);
    }

    public function testExplicitBodySequencesDefineCanonicalSequenceBody(): void
    {
        $message = new Message(
            body: '',
            bodySection: MessageBodySection::AmqpSequence,
            bodySequence: ['legacy'],
            bodySequences: [
                ['one'],
                ['two', 2],
            ],
        );

        self::assertSame(['one'], $message->bodySequence);
        self::assertSame([['one'], ['two', 2]], $message->bodySequences);
        self::assertSame([], $message->dataSections);
    }

    public function testRejectsDataSectionsForNonDataBody(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('DATA body sections require the DATA body section type.');

        new Message(
            body: '',
            bodySection: MessageBodySection::AmqpSequence,
            dataSections: ['one'],
        );
    }

    public function testRejectsBodySequencesForNonSequenceBody(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('AMQP sequence body sections require the AMQP sequence body section type.');

        new Message(
            body: '',
            bodySequences: [
                ['one'],
            ],
        );
    }
}
