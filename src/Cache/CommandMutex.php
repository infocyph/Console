<?php

declare(strict_types=1);

namespace Infocyph\Console\Cache;

use Infocyph\CacheLayer\Cache\Lock\LockHandle;
use Infocyph\CacheLayer\Cache\Lock\LockProviderInterface;

final readonly class CommandMutex
{
    public function __construct(private LockProviderInterface $locks, private string $prefix = 'console:mutex:') {}

    public function acquire(string $name, float $waitSeconds = 0.0, float $leaseSeconds = 300.0): ?LockHandle
    {
        return $this->locks->acquire($this->prefix . $name, $waitSeconds, $leaseSeconds);
    }

    /**
     * @param callable(): mixed $operation
     */
    public function attempt(
        string $name,
        callable $operation,
        float $waitSeconds = 0.0,
        float $leaseSeconds = 300.0,
    ): CommandMutexResult {
        $handle = $this->acquire($name, $waitSeconds, $leaseSeconds);
        if ($handle === null) {
            return CommandMutexResult::skipped();
        }

        try {
            return CommandMutexResult::acquired($operation());
        } finally {
            $this->release($handle);
        }
    }

    public function refresh(?LockHandle $handle, float $leaseSeconds): bool
    {
        return $this->locks->refresh($handle, $leaseSeconds);
    }

    public function release(?LockHandle $handle): void
    {
        $this->locks->release($handle);
    }

    /**
     * @template T
     * @param callable(): T $operation
     * @return T
     */
    public function synchronized(
        string $name,
        callable $operation,
        float $waitSeconds = 0.0,
        float $leaseSeconds = 300.0,
    ): mixed {
        $result = $this->attempt($name, $operation, $waitSeconds, $leaseSeconds);
        if (!$result->acquired) {
            throw new \RuntimeException(sprintf('Could not acquire command mutex "%s".', $name));
        }

        return $result->value;
    }
}
