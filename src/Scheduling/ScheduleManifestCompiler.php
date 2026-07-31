<?php

declare(strict_types=1);

namespace Infocyph\Console\Scheduling;

use Infocyph\Console\Support\PhpManifestWriter;

/** @internal */
final class ScheduleManifestCompiler
{
    /** @return list<array{command:string,arguments:list<string>,cron:string,timezone:string,without_overlap:bool,on_one_server:bool,overlap_wait_seconds:float,overlap_lease_seconds:float,timeout_seconds:?float,idle_timeout_seconds:?float,termination_grace_seconds:float,memory_limit_megabytes:?int}> */
    public function compile(Schedule $schedule): array
    {
        return array_map(static fn(ScheduledCommand $entry): array => $entry->toManifest(), $schedule->entries());
    }

    public function write(Schedule $schedule, string $path): void
    {
        PhpManifestWriter::write($this->compile($schedule), $path, 'schedule manifest');
    }
}
