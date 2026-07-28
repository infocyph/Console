<?php

declare(strict_types=1);

namespace Infocyph\Console\Scheduling;

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
        $directory = dirname($path);
        if (!is_dir($directory) && !mkdir($directory, 0777, true) && !is_dir($directory)) {
            throw new \RuntimeException(sprintf('Unable to create schedule manifest directory "%s".', $directory));
        }
        $temporary = $path . '.' . bin2hex(random_bytes(6)) . '.tmp';
        file_put_contents($temporary, "<?php\n\ndeclare(strict_types=1);\n\nreturn " . var_export($this->compile($schedule), true) . ";\n", LOCK_EX);
        if (!rename($temporary, $path)) {
            if (is_file($temporary)) {
                unlink($temporary);
            }

            throw new \RuntimeException(sprintf('Unable to publish schedule manifest "%s".', $path));
        }
    }
}
