<?php

declare(strict_types=1);

namespace Infocyph\Console\Cache;

final readonly class CommandMutexResult
{
    private function __construct(public bool $acquired, public mixed $value = null) {}

    public static function acquired(mixed $value): self
    {
        return new self(true, $value);
    }

    public static function skipped(): self
    {
        return new self(false);
    }
}
