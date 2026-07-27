<?php

declare(strict_types=1);

namespace Infocyph\Console\Scheduling;

use Infocyph\Console\Cache\CommandMutex;
use Infocyph\UID\Id;

final readonly class ScheduleRunner
{
    public function __construct(private ?CommandMutex $mutex = null, private ?ScheduleStateRepository $state = null) {}

    /**
     * @param callable(string): int $executor
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
            $operation = fn(): ScheduleRun => $this->run($entry, $executor, $now);
            $run = $operation;
            if ($entry->preventsOverlap()) {
                $mutex = $this->mutex ?? throw new \LogicException('Schedules using withoutOverlap require a CommandMutex.');
                $previous = $run;
                $run = fn(): ScheduleRun => $mutex->synchronized('schedule:' . $entry->command(), $previous);
            }
            if ($entry->requiresSingleServer()) {
                $mutex = $this->mutex ?? throw new \LogicException('Schedules using onOneServer require a CommandMutex.');
                $previous = $run;
                $run = fn(): ScheduleRun => $mutex->synchronized('schedule:single-server:' . $entry->command(), $previous);
            }
            $runs[] = $run();
        }

        return $runs;
    }

    /** @param callable(string): int $executor */
    private function run(ScheduledCommand $entry, callable $executor, \DateTimeInterface $now): ScheduleRun
    {
        try {
            $exitCode = $executor($entry->command());
        } catch (\Throwable) {
            $exitCode = 1;
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
}
