<?php

declare(strict_types=1);

namespace Infocyph\Console\Worker;

use Infocyph\Console\Terminal\SignalManager;

final class WorkerSupervisor
{
    private bool $interrupted = false;

    public function __construct(?SignalManager $signals = null)
    {
        $signals ??= new SignalManager();
        $signals->onInterrupt(function (): void {
            $this->interrupted = true;
        });
        $signals->register();
    }

    /**
     * @param list<string> $command
     */
    public function run(
        array $command,
        WorkloadProbe $workload,
        ?WorkerOptions $options = null,
        ?callable $heartbeat = null,
    ): WorkerRunSummary {
        if ($command === [] || $command[0] === '') {
            throw new \InvalidArgumentException('A worker command is required.');
        }

        $options ??= new WorkerOptions();
        $startedAt = microtime(true);
        $nextScaleAt = $startedAt;
        $started = $completed = $failed = $forced = 0;
        $processes = [];
        $this->interrupted = false;

        while (true) {
            $now = microtime(true);
            $this->reap($processes, $completed, $failed);
            $forced += $this->enforceLimits($processes, $options, $now, $startedAt);
            $pending = max(0, $workload->pending());
            if ($heartbeat !== null && !$heartbeat()) {
                $this->interrupted = true;
            }

            if ($this->shouldStop($options, $startedAt, $now)) {
                $this->stopAll($processes, $now);
            } elseif ($now >= $nextScaleAt) {
                $desired = $this->desiredProcesses($pending, $options);
                $started += $this->scaleUp($processes, $command, $options, $desired, $started);
                if ($options->scaleDownProcesses) {
                    $this->scaleDown($processes, $desired, $now);
                }
                $nextScaleAt = $now + $options->scaleCooldownSeconds;
            }

            if ($processes === [] && $this->finished($options, $pending, $started, $startedAt, $now)) {
                break;
            }

            usleep((int) min(1_000_000, max(10_000, $options->pollIntervalSeconds * 1_000_000)));
        }

        return new WorkerRunSummary(
            $started,
            $completed,
            $failed,
            $forced,
            $this->interrupted,
            microtime(true) - $startedAt,
        );
    }

    private function desiredProcesses(int $pending, WorkerOptions $options): int
    {
        $needed = (int) ceil($pending / $options->jobsPerProcess);

        return min($options->maximumProcesses, max($options->minimumProcesses, $needed));
    }

    /** @param array<int, WorkerProcess> $processes */
    private function enforceLimits(
        array $processes,
        WorkerOptions $options,
        float $now,
        float $supervisorStartedAt,
    ): int {
        $stopAll = $this->interrupted
            || ($options->supervisorMaxSeconds !== null
                && $now - $supervisorStartedAt >= $options->supervisorMaxSeconds);
        $forced = 0;
        foreach ($processes as $process) {
            $expired = $options->processMaxSeconds !== null
                && $now - $process->startedAt >= $options->processMaxSeconds;
            if ($stopAll || $expired) {
                $process->stop($now);
            }
            if ($process->shouldForce($now, $options->terminationGraceSeconds)) {
                $forced += $process->force() ? 1 : 0;
            }
        }

        return $forced;
    }

    private function finished(
        WorkerOptions $options,
        int $pending,
        int $started,
        float $startedAt,
        float $now,
    ): bool {
        return $this->interrupted
            || ($options->stopWhenEmpty && $pending === 0)
            || ($options->maximumProcessesStarted !== null && $started >= $options->maximumProcessesStarted)
            || ($options->supervisorMaxSeconds !== null && $now - $startedAt >= $options->supervisorMaxSeconds);
    }

    /**
     * @param array<int, WorkerProcess> $processes
     * @param-out array<int, WorkerProcess> $processes
     * @param-out int $completed
     * @param-out int $failed
     */
    private function reap(array &$processes, int &$completed, int &$failed): void
    {
        foreach ($processes as $id => $process) {
            $status = $process->status();
            if ($status['running']) {
                continue;
            }
            $exitCode = $process->close($status['exitcode']);
            $completed++;
            $failed += $exitCode === 0 ? 0 : 1;
            unset($processes[$id]);
        }
    }

    /** @param array<int, WorkerProcess> $processes */
    private function scaleDown(array $processes, int $desired, float $now): void
    {
        $excess = count($processes) - $desired;
        if ($excess <= 0) {
            return;
        }
        foreach (array_slice($processes, $desired, $excess, true) as $process) {
            $process->stop($now);
        }
    }

    /**
     * @param array<int, WorkerProcess> $processes
     * @param-out array<int, WorkerProcess> $processes
     * @param list<string> $command
     */
    private function scaleUp(
        array &$processes,
        array $command,
        WorkerOptions $options,
        int $desired,
        int $started,
    ): int {
        $capacity = $desired - count($processes);
        if ($options->maximumProcessesStarted !== null) {
            $capacity = min($capacity, $options->maximumProcessesStarted - $started);
        }
        $count = min($options->scaleStep, max(0, $capacity));
        for ($index = 0; $index < $count; $index++) {
            $process = $this->start($command, $options);
            $processes[spl_object_id($process)] = $process;
        }

        return $count;
    }

    private function shouldStop(
        WorkerOptions $options,
        float $startedAt,
        float $now,
    ): bool {
        return $this->interrupted
            || ($options->supervisorMaxSeconds !== null && $now - $startedAt >= $options->supervisorMaxSeconds);
    }

    /** @param list<string> $command */
    private function start(array $command, WorkerOptions $options): WorkerProcess
    {
        $environment = $options->environment === []
            ? null
            : array_replace(getenv(), $options->environment);
        $process = proc_open(
            $command,
            [0 => STDIN, 1 => STDOUT, 2 => STDERR],
            $pipes,
            $options->workingDirectory,
            $environment,
        );
        if (!is_resource($process)) {
            throw new \RuntimeException('Worker process could not be started.');
        }

        return new WorkerProcess($process, microtime(true));
    }

    /** @param array<int, WorkerProcess> $processes */
    private function stopAll(array $processes, float $now): void
    {
        foreach ($processes as $process) {
            $process->stop($now);
        }
    }
}
