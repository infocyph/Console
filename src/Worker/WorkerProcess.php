<?php

declare(strict_types=1);

namespace Infocyph\Console\Worker;

/**
 * @internal
 */
final class WorkerProcess
{
    private bool $forced = false;

    private ?float $stoppingAt = null;

    /** @param resource $process */
    public function __construct(private readonly mixed $process, public readonly float $startedAt) {}

    public function close(int $reportedExitCode): int
    {
        $closed = proc_close($this->process);

        return $reportedExitCode >= 0 ? $reportedExitCode : $closed;
    }

    public function force(): bool
    {
        if ($this->forced) {
            return false;
        }
        proc_terminate($this->process, defined('SIGKILL') ? SIGKILL : 9);
        $this->forced = true;

        return true;
    }

    public function shouldForce(float $now, float $graceSeconds): bool
    {
        return !$this->forced
            && $this->stoppingAt !== null
            && $now - $this->stoppingAt >= $graceSeconds;
    }

    /** @return array{running: bool, exitcode: int} */
    public function status(): array
    {
        $status = proc_get_status($this->process);

        return ['running' => $status['running'], 'exitcode' => $status['exitcode']];
    }

    public function stop(float $now): void
    {
        if ($this->stoppingAt !== null) {
            return;
        }
        proc_terminate($this->process, defined('SIGTERM') ? SIGTERM : 15);
        $this->stoppingAt = $now;
    }
}
