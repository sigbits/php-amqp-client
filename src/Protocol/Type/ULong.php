<?php

declare(strict_types=1);

namespace Sigbits\Amqp\Protocol\Type;

use InvalidArgumentException;

final readonly class ULong
{
    private const string MAX_VALUE = '18446744073709551615';

    public string $value;

    public function __construct(int|string $value)
    {
        $normalized = $this->normalize($value);

        if ($this->isGreaterThanMaximum($normalized)) {
            throw new InvalidArgumentException('AMQP ulong value must be between 0 and 18446744073709551615.');
        }

        $this->value = $normalized;
    }

    private function normalize(int|string $value): string
    {
        if (is_int($value)) {
            if ($value < 0) {
                throw new InvalidArgumentException('AMQP ulong value must be between 0 and 18446744073709551615.');
            }

            return (string) $value;
        }

        if ($value === '' || preg_match('/\D/', $value) === 1) {
            throw new InvalidArgumentException('AMQP ulong value must be an unsigned decimal string.');
        }

        $normalized = ltrim($value, '0');

        return $normalized === '' ? '0' : $normalized;
    }

    private function isGreaterThanMaximum(string $value): bool
    {
        if (strlen($value) !== strlen(self::MAX_VALUE)) {
            return strlen($value) > strlen(self::MAX_VALUE);
        }

        return strcmp($value, self::MAX_VALUE) > 0;
    }
}
