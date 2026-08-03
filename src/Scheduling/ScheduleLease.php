<?php

declare(strict_types=1);

namespace Infocyph\Console\Scheduling;

final class ScheduleLease
{
    private float $nextRefresh;

    /**
     * @param \Closure $refresh Callback that refreshes the active lease.
     * @phpstan-param \Closure(): bool $refresh
     * @psalm-param \Closure(): bool $refresh
     */
    public function __construct(private readonly \Closure $refresh, private readonly float $leaseSeconds)
    {
        if ($leaseSeconds <= 0) {
            throw new \InvalidArgumentException('Schedule lease duration must be positive.');
        }
        $this->nextRefresh = microtime(true) + max(0.1, $leaseSeconds / 3);
    }

    public function heartbeat(): bool
    {
        if (microtime(true) < $this->nextRefresh) {
            return true;
        }
        $this->nextRefresh = microtime(true) + max(0.1, $this->leaseSeconds / 3);

        return ($this->refresh)();
    }
}
