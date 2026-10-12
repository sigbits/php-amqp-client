<?php

declare(strict_types=1);

namespace Sigbits\Amqp\Protocol\Type;

use InvalidArgumentException;

final readonly class Char
{
    public function __construct(
        public int $codePoint,
    ) {
        if ($codePoint < 0 || $codePoint > 0x10ffff || ($codePoint >= 0xd800 && $codePoint <= 0xdfff)) {
            throw new InvalidArgumentException('AMQP char value must be a Unicode scalar value between U+0000 and U+10FFFF.');
        }
    }
}
