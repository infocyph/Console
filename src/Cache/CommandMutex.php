<?php

declare(strict_types=1);

namespace Infocyph\Console\Cache;

use Infocyph\CacheLayer\Cache\Lock\LockProviderInterface;

final readonly class CommandMutex
{
    public function __construct(private LockProviderInterface $locks, private string $prefix = 'console:mutex:') {}

    public function synchronized(string $name, callable $operation, float $waitSeconds = 0.0): mixed
    {
        $handle = $this->locks->acquire($this->prefix . $name, $waitSeconds);
        if ($handle === null) {
            throw new \RuntimeException(sprintf('Could not acquire command mutex "%s".', $name));
        }

        try {
            return $operation();
        } finally {
            $this->locks->release($handle);
        }
    }
}
