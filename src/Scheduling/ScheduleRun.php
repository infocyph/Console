<?php

declare(strict_types=1);

namespace Infocyph\Console\Scheduling;

final readonly class ScheduleRun
{
    public function __construct(
        public string $id,
        public string $command,
        public int $scheduledAt,
        public int $finishedAt,
        public int $exitCode,
        public ScheduleRunStatus $status = ScheduleRunStatus::COMPLETED,
    ) {}

    public function skipped(): bool
    {
        return $this->status === ScheduleRunStatus::SKIPPED;
    }

    public function successful(): bool
    {
        return !$this->skipped() && $this->exitCode === 0;
    }
}
