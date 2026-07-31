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
        $nextStartAt = $startedAt;
        $started = $completed = $failed = $forced = $consecutiveFailures = 0;
        $processes = [];
        $this->interrupted = false;

        try {
            $stopReason = $this->supervise(
                $processes,
                $command,
                $workload,
                $options,
                $heartbeat,
                $startedAt,
                $nextScaleAt,
                $nextStartAt,
                $started,
                $completed,
                $failed,
                $forced,
                $consecutiveFailures,
            );
        } catch (\Throwable $exception) {
            $this->drainAfterFailure($processes, $options);

            throw $exception;
        }

        return new WorkerRunSummary(
            $started,
            $completed,
            $failed,
            $forced,
            $this->interrupted,
            microtime(true) - $startedAt,
            $stopReason,
        );
    }

    /**
     * @param array<int, WorkerProcess> $processes
     * @param list<string> $command
     */
    private function applyScaleDecision(
        array &$processes,
        array $command,
        WorkerOptions $options,
        ?WorkerStopReason $stopReason,
        int $pending,
        float $now,
        float $nextScaleAt,
        float $nextStartAt,
        int &$started,
    ): float {
        if ($stopReason !== null) {
            $this->stopAll($processes, $now);

            return $nextScaleAt;
        }
        if ($now < $nextScaleAt) {
            return $nextScaleAt;
        }

        $desired = $this->desiredProcesses($pending, $options);
        if ($now >= $nextStartAt) {
            $started += $this->scaleUp($processes, $command, $options, $desired, $started);
        }
        if ($options->scaleDownProcesses) {
            $this->scaleDown($processes, $desired, $now);
        }

        return $now + $options->scaleCooldownSeconds;
    }

    private function desiredProcesses(int $pending, WorkerOptions $options): int
    {
        $needed = (int) ceil($pending / $options->jobsPerProcess);

        return min($options->maximumProcesses, max($options->minimumProcesses, $needed));
    }

    /** @param array<int, WorkerProcess> $processes */
    private function drainAfterFailure(array &$processes, WorkerOptions $options): void
    {
        $startedAt = microtime(true);
        $deadline = $startedAt + $options->terminationGraceSeconds + 1.0;
        $completed = $failed = 0;
        while ($processes !== []) {
            $now = microtime(true);
            $this->stopAll($processes, $startedAt);
            foreach ($processes as $process) {
                if ($process->shouldForce($now, $options->terminationGraceSeconds) || $now >= $deadline) {
                    $process->force();
                }
            }
            $this->reap($processes, $completed, $failed);
            if ($processes === [] || $now >= $deadline) {
                break;
            }
            usleep($this->pollMicroseconds($options));
        }
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

    /** @param array<int, WorkerProcess> $processes */
    private function finishedReason(
        array $processes,
        WorkerOptions $options,
        ?WorkerStopReason $stopReason,
        int $started,
    ): ?WorkerStopReason {
        if ($processes !== []) {
            return null;
        }
        if ($stopReason !== null) {
            return $stopReason;
        }
        if ($options->maximumProcessesStarted !== null && $started >= $options->maximumProcessesStarted) {
            return WorkerStopReason::START_LIMIT;
        }

        return null;
    }

    private function limitReason(
        WorkerOptions $options,
        float $startedAt,
        float $now,
    ): ?WorkerStopReason {
        if ($this->interrupted) {
            return WorkerStopReason::INTERRUPTED;
        }
        if ($options->supervisorMaxSeconds !== null && $now - $startedAt >= $options->supervisorMaxSeconds) {
            return WorkerStopReason::SUPERVISOR_LIMIT;
        }

        return null;
    }

    /**
     * @param array<int, WorkerProcess> $processes
     * @param-out array<int, WorkerProcess> $processes
     */
    private function observeProcesses(
        array &$processes,
        WorkerOptions $options,
        float $now,
        float &$nextStartAt,
        int &$completed,
        int &$failed,
        int &$consecutiveFailures,
    ): void {
        $completedBefore = $completed;
        $failedBefore = $failed;
        $this->reap($processes, $completed, $failed);
        $completedDelta = $completed - $completedBefore;
        $failedDelta = $failed - $failedBefore;
        if ($failedDelta > 0) {
            $consecutiveFailures += $failedDelta;
            $nextStartAt = max($nextStartAt, $now + $options->failureBackoffSeconds);

            return;
        }
        if ($completedDelta > 0) {
            $consecutiveFailures = 0;
        }
    }

    private function pollMicroseconds(WorkerOptions $options): int
    {
        return (int) min(1_000_000, max(10_000, $options->pollIntervalSeconds * 1_000_000));
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

    /**
     * @param array<int, WorkerProcess> $processes
     * @return array{?WorkerStopReason, int}
     */
    private function stopDecision(
        array $processes,
        WorkloadProbe $workload,
        WorkerOptions $options,
        ?callable $heartbeat,
        float $startedAt,
        float $now,
        int $consecutiveFailures,
        ?WorkerStopReason $previousReason,
    ): array {
        if ($previousReason !== null) {
            return [$previousReason, 0];
        }

        $reason = $this->limitReason($options, $startedAt, $now);
        if ($reason !== null) {
            return [$reason, 0];
        }
        if ($heartbeat !== null && !$heartbeat()) {
            $this->interrupted = true;

            return [WorkerStopReason::HEARTBEAT_LOST, 0];
        }

        $pending = max(0, $workload->pending());
        if (
            $options->maximumConsecutiveFailures !== null
            && $consecutiveFailures >= $options->maximumConsecutiveFailures
        ) {
            return [WorkerStopReason::FAILURE_LIMIT, $pending];
        }
        if ($options->stopWhenEmpty && $pending === 0 && $processes === []) {
            return [WorkerStopReason::EMPTY, 0];
        }

        return [null, $pending];
    }

    /**
     * @param array<int, WorkerProcess> $processes
     * @param-out array<int, WorkerProcess> $processes
     * @param list<string> $command
     */
    private function supervise(
        array &$processes,
        array $command,
        WorkloadProbe $workload,
        WorkerOptions $options,
        ?callable $heartbeat,
        float $startedAt,
        float &$nextScaleAt,
        float &$nextStartAt,
        int &$started,
        int &$completed,
        int &$failed,
        int &$forced,
        int &$consecutiveFailures,
    ): WorkerStopReason {
        $stopReason = null;
        while (true) {
            $now = microtime(true);
            $this->observeProcesses(
                $processes,
                $options,
                $now,
                $nextStartAt,
                $completed,
                $failed,
                $consecutiveFailures,
            );
            $forced += $this->enforceLimits($processes, $options, $now, $startedAt);
            [$stopReason, $pending] = $this->stopDecision(
                $processes,
                $workload,
                $options,
                $heartbeat,
                $startedAt,
                $now,
                $consecutiveFailures,
                $stopReason,
            );
            $nextScaleAt = $this->applyScaleDecision(
                $processes,
                $command,
                $options,
                $stopReason,
                $pending,
                $now,
                $nextScaleAt,
                $nextStartAt,
                $started,
            );
            $finished = $this->finishedReason($processes, $options, $stopReason, $started);
            if ($finished !== null) {
                return $finished;
            }

            usleep($this->pollMicroseconds($options));
        }
    }
}
