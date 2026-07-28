<?php

declare(strict_types=1);

namespace Infocyph\Console\Scheduling;

use Infocyph\Console\Cache\CommandMutex;
use Infocyph\Console\Command\ExitCode;
use Infocyph\UID\Id;

final readonly class ScheduleRunner
{
    public function __construct(private ?CommandMutex $mutex = null, private ?ScheduleStateRepository $state = null) {}

    /**
     * @param callable(string, ScheduledCommand, ?ScheduleLease): int $executor
     * @return list<ScheduleRun>
     */
    public function runDue(Schedule $schedule, callable $executor, ?\DateTimeInterface $now = null): array
    {
        $now ??= new \DateTimeImmutable();
        $runs = [];
        foreach ($schedule->entries() as $entry) {
            if (!$entry->due($now)) {
                continue;
            }
            $runs[] = $this->runEntry($entry, $executor, $now);
        }

        return $runs;
    }

    private function mutexName(ScheduledCommand $entry): string
    {
        $scope = $entry->requiresSingleServer() ? 'single-server:' : 'overlap:';

        return 'schedule:' . $scope . hash('sha256', serialize($entry->toManifest()));
    }

    /** @param callable(string, ScheduledCommand, ?ScheduleLease): int $executor */
    private function run(
        ScheduledCommand $entry,
        callable $executor,
        \DateTimeInterface $now,
        ?ScheduleLease $lease = null,
    ): ScheduleRun {
        try {
            $exitCode = $executor($entry->command(), $entry, $lease);
        } catch (\Throwable) {
            $exitCode = ExitCode::FAILURE;
        }
        $run = new ScheduleRun(Id::uuid(), $entry->command(), $now->getTimestamp(), time(), $exitCode);
        $this->state?->record($run);
        if ($run->successful()) {
            $entry->success($run);
        } else {
            $entry->failure($run);
        }

        return $run;
    }

    /**
     * @param callable(string, ScheduledCommand, ?ScheduleLease): int $executor
     */
    private function runEntry(
        ScheduledCommand $entry,
        callable $executor,
        \DateTimeInterface $now,
    ): ScheduleRun {
        if (!$entry->preventsOverlap() && !$entry->requiresSingleServer()) {
            return $this->run($entry, $executor, $now);
        }

        $mutex = $this->mutex ?? throw new \LogicException('Locked schedules require a CommandMutex.');
        $handle = $mutex->acquire(
            $this->mutexName($entry),
            $entry->overlapWaitSeconds(),
            $entry->overlapLeaseSeconds(),
        );
        if ($handle === null) {
            return $this->skipped($entry, $now);
        }

        try {
            $lease = new ScheduleLease(
                fn(): bool => $mutex->refresh($handle, $entry->overlapLeaseSeconds()),
                $entry->overlapLeaseSeconds(),
            );

            return $this->run($entry, $executor, $now, $lease);
        } finally {
            $mutex->release($handle);
        }
    }

    private function skipped(ScheduledCommand $entry, \DateTimeInterface $now): ScheduleRun
    {
        $run = new ScheduleRun(
            Id::uuid(),
            $entry->command(),
            $now->getTimestamp(),
            time(),
            ExitCode::SUCCESS,
            ScheduleRunStatus::SKIPPED,
        );
        $this->state?->record($run);

        return $run;
    }
}
