<?php

declare(strict_types=1);

namespace Sigbits\Amqp\Tests\Client;

use PHPUnit\Framework\TestCase;
use ReflectionClass;
use ReflectionMethod;
use ReflectionNamedType;
use ReflectionParameter;
use ReflectionType;
use ReflectionUnionType;
use Sigbits\Amqp\Client\ClientException;
use Sigbits\Amqp\Client\Connection;
use Sigbits\Amqp\Client\Delivery;
use Sigbits\Amqp\Client\Receiver;
use Sigbits\Amqp\Client\Sender;
use Sigbits\Amqp\Client\Session;
use Sigbits\Amqp\Transport\ConnectionUri;
use Sigbits\Amqp\Transport\TlsOptions;
use Sigbits\Amqp\Transport\TransportException;

final class PublicApiStabilityTest extends TestCase
{
    public function testUserFacingCandidateSurfaceMatchesV100Audit(): void
    {
        self::assertSame([
            Connection::class => [
                'static connect(string $uri, ?Sigbits\Amqp\Protocol\Sasl\SaslClient $saslClient = null, string $containerId = \'sigbits-php-amqp-client\', float $timeoutSeconds = 30.0, ?Sigbits\Amqp\Transport\TlsOptions $tls = null): self',
                'state(): Sigbits\Amqp\Engine\ConnectionState',
                'beginSession(int $channel = 0): Sigbits\Amqp\Client\Session',
                'close(): void',
            ],
            Session::class => [
                'state(): Sigbits\Amqp\Engine\SessionState',
                'openSender(string $address, string $name = \'sender\', int $handle = 0): Sigbits\Amqp\Client\Sender',
                'openReceiver(string $address, string $name = \'receiver\', int $handle = 1, int $credit = 1): Sigbits\Amqp\Client\Receiver',
                'end(): void',
            ],
            Sender::class => [
                'state(): Sigbits\Amqp\Engine\SenderLinkState',
                'availableCredit(): int',
                'send(Sigbits\Amqp\Protocol\Message\Message|string $message, int $maxFrameSize = 512): void',
                'detach(): void',
            ],
            Receiver::class => [
                'state(): Sigbits\Amqp\Engine\ReceiverLinkState',
                'receive(int $timeoutMilliseconds): ?Sigbits\Amqp\Protocol\Message\Message',
                'receiveDelivery(int $timeoutMilliseconds): ?Sigbits\Amqp\Client\Delivery',
                'grantCredit(int $credit): void',
                'detach(): void',
            ],
            Delivery::class => [
                'message(): Sigbits\Amqp\Protocol\Message\Message',
                'accept(): void',
                'release(): void',
                'reject(): void',
            ],
            ConnectionUri::class => [
                'static parse(string $uri, ?Sigbits\Amqp\Transport\TlsOptions $tls = null): self',
                'usesTls(): bool',
                'streamContextOptions(): array',
            ],
            TlsOptions::class => [
                'streamContextOptions(string $defaultPeerName): array',
            ],
            ClientException::class => [
                'static senderLinkDetached(): self',
                'static senderLinkCreditExhausted(?Throwable $previous = null): self',
                'static senderLinkDetachedDuringOpen(?Sigbits\Amqp\Protocol\Performative\PerformativeError $error = null): self',
                'static receiverLinkDetached(): self',
                'static receiverLinkDetachedDuringSettlement(): self',
                'static receiverLinkDetachedDuringOpen(?Sigbits\Amqp\Protocol\Performative\PerformativeError $error = null): self',
                'static remoteConnectionClosed(): self',
                'static remoteSessionEnded(): self',
                'static deliveryAlreadySettled(): self',
            ],
            TransportException::class => [
                'static unsupportedScheme(): self',
                'static missingHost(): self',
                'static connectionFailed(string $target, string $reason): self',
                'static unexpectedEndOfStream(): self',
                'static readTimedOut(): self',
                'static writeFailed(): self',
                'static unexpectedFrameType(int $type): self',
            ],
        ], self::declaredPublicMethodSignatures([
            Connection::class,
            Session::class,
            Sender::class,
            Receiver::class,
            Delivery::class,
            ConnectionUri::class,
            TlsOptions::class,
            ClientException::class,
            TransportException::class,
        ]));
    }

    public function testImplementationConstructorsAreNotPublicApi(): void
    {
        $audit = self::v100ApiStabilityAudit();

        self::assertSame([
            Session::class => '__construct(Sigbits\Amqp\Engine\SessionEngine $engine, int $channel, callable $writeAll, callable $readFrame)',
            Sender::class => '__construct(Sigbits\Amqp\Engine\SenderLinkEngine $engine, callable $writeAll, callable $readFrame)',
            Receiver::class => '__construct(Sigbits\Amqp\Engine\ReceiverLinkEngine $link, callable $read, ?callable $writeAll = null)',
            Delivery::class => '__construct(int $deliveryId, Sigbits\Amqp\Protocol\Message\Message $message, callable $accept, callable $release, callable $reject)',
        ], self::nonPublicConstructorSignatures([
            Session::class => '__construct(Sigbits\Amqp\Engine\SessionEngine $engine, int $channel, callable $writeAll, callable $readFrame)',
            Sender::class => '__construct(Sigbits\Amqp\Engine\SenderLinkEngine $engine, callable $writeAll, callable $readFrame)',
            Receiver::class => '__construct(Sigbits\Amqp\Engine\ReceiverLinkEngine $link, callable $read, ?callable $writeAll = null)',
            Delivery::class => '__construct(int $deliveryId, Sigbits\Amqp\Protocol\Message\Message $message, callable $accept, callable $release, callable $reject)',
        ]));
        self::assertSame([
            TlsOptions::class => '__construct(bool $verifyPeer = true, bool $verifyPeerName = true, ?string $peerName = null, ?string $cafile = null, ?string $localCert = null)',
        ], self::publicConstructorSignatures([
            TlsOptions::class,
        ]));
        self::assertStringContainsString('Session, Sender, Receiver, and Delivery constructors are hidden from the supported public API', $audit);
    }

    /**
     * @param list<class-string> $classes
     *
     * @return array<class-string, list<string>>
     */
    private static function declaredPublicMethodSignatures(array $classes): array
    {
        $signatures = [];

        foreach ($classes as $class) {
            $reflection = new ReflectionClass($class);
            $signatures[$class] = [];

            foreach ($reflection->getMethods(ReflectionMethod::IS_PUBLIC) as $method) {
                if ($method->isConstructor() || $method->getDeclaringClass()->getName() !== $class) {
                    continue;
                }

                $signatures[$class][] = self::methodSignature($method);
            }
        }

        return $signatures;
    }

    /**
     * @param list<class-string> $classes
     *
     * @return array<class-string, string>
     */
    private static function publicConstructorSignatures(array $classes): array
    {
        $signatures = [];

        foreach ($classes as $class) {
            $constructor = (new ReflectionClass($class))->getConstructor();

            if ($constructor === null || !$constructor->isPublic()) {
                continue;
            }

            $signatures[$class] = self::methodSignature($constructor);
        }

        return $signatures;
    }

    /**
     * @param array<class-string, string> $constructors
     *
     * @return array<class-string, string>
     */
    private static function nonPublicConstructorSignatures(array $constructors): array
    {
        $signatures = [];

        foreach ($constructors as $class => $_expectedSignature) {
            $constructor = (new ReflectionClass($class))->getConstructor();

            if ($constructor === null) {
                continue;
            }

            if ($constructor->isPublic()) {
                continue;
            }

            $signatures[$class] = self::methodSignature($constructor);
        }

        return $signatures;
    }

    private static function methodSignature(ReflectionMethod $method): string
    {
        $prefix = $method->isStatic() ? 'static ' : '';
        $parameters = array_map(
            static fn (ReflectionParameter $parameter): string => self::parameterSignature($parameter),
            $method->getParameters(),
        );

        return sprintf(
            '%s%s(%s)%s',
            $prefix,
            $method->getName(),
            implode(', ', $parameters),
            self::returnTypeSignature($method),
        );
    }

    private static function parameterSignature(ReflectionParameter $parameter): string
    {
        $signature = self::typeSignature($parameter->getType());
        $signature .= ($signature === '' ? '' : ' ') . '$' . $parameter->getName();

        if ($parameter->isDefaultValueAvailable()) {
            $signature .= ' = ' . self::defaultValueSignature($parameter->getDefaultValue());
        }

        return $signature;
    }

    private static function defaultValueSignature(mixed $value): string
    {
        if ($value === null) {
            return 'null';
        }

        return var_export($value, true);
    }

    private static function returnTypeSignature(ReflectionMethod $method): string
    {
        $type = $method->getReturnType();

        if ($type === null) {
            return '';
        }

        return ': ' . self::typeSignature($type, $method->getDeclaringClass()->getName());
    }

    private static function typeSignature(?ReflectionType $type, ?string $selfClass = null): string
    {
        if ($type === null) {
            return '';
        }

        if ($type instanceof ReflectionUnionType) {
            return implode('|', array_map(
                static fn (ReflectionNamedType $namedType): string => self::namedTypeSignature($namedType, $selfClass),
                $type->getTypes(),
            ));
        }

        if (!$type instanceof ReflectionNamedType) {
            self::fail(sprintf('Unsupported reflection type %s.', $type::class));
        }

        return self::namedTypeSignature($type, $selfClass);
    }

    private static function namedTypeSignature(ReflectionNamedType $type, ?string $selfClass): string
    {
        $name = $type->getName();

        if ($selfClass !== null && $name === $selfClass) {
            $name = 'self';
        }

        if ($type->allowsNull() && $name !== 'mixed' && $name !== 'null') {
            return '?' . $name;
        }

        return $name;
    }

    private static function v100ApiStabilityAudit(): string
    {
        $contents = file_get_contents(dirname(__DIR__, 2) . '/docs/api-stability-v1.0.0.md');

        if ($contents === false) {
            self::fail('Could not read v1.0.0 public API stability audit.');
        }

        return $contents;
    }
}
