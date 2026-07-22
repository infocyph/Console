<?php

declare(strict_types=1);

namespace Infocyph\Console\Scheduling;

use Infocyph\DBLayer\DB;

final readonly class DBLayerScheduleStateRepository implements ScheduleStateRepository
{
    public function __construct(private string $table = 'console_schedule_runs', private ?string $connection = null) {}

    public function record(ScheduleRun $run): void
    {
        DB::table($this->table, $this->connection)->insert(['id' => $run->id, 'command' => $run->command, 'scheduled_at' => $run->scheduledAt, 'finished_at' => $run->finishedAt, 'exit_code' => $run->exitCode]);
    }
}
