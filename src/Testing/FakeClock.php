<?php

declare(strict_types=1);

namespace Infocyph\Console\Testing;

final class FakeClock
{
    public function __construct(private int $timestamp = 0) {}

    public function advance(int $seconds): void
    {
        if ($seconds < 0) {
            throw new \InvalidArgumentException('A fake clock cannot move backwards.');
        }

        $this->timestamp += $seconds;
    }

    public function now(): int
    {
        return $this->timestamp;
    }
}
