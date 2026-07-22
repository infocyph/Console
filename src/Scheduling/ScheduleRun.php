<?php

declare(strict_types=1);

namespace Infocyph\Console\Scheduling;

final readonly class ScheduleRun
{
    public function __construct(public string $id, public string $command, public int $scheduledAt, public int $finishedAt, public int $exitCode) {}

    public function successful(): bool
    {
        return $this->exitCode === 0;
    }
}
