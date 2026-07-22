<?php

declare(strict_types=1);

namespace Infocyph\Console\Testing;

final class FakeSignalManager
{
    /** @var list<callable(): void> */
    private array $interruptCallbacks = [];

    public function interrupt(): void
    {
        foreach ($this->interruptCallbacks as $callback) {
            $callback();
        }
    }

    public function onInterrupt(callable $callback): void
    {
        $this->interruptCallbacks[] = $callback;
    }
}
