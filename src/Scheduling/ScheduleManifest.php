<?php

declare(strict_types=1);

namespace Infocyph\Console\Scheduling;

/** @internal */
final class ScheduleManifest
{
    public static function load(string $path): Schedule
    {
        if (!is_file($path)) {
            throw new \InvalidArgumentException(sprintf('Schedule manifest "%s" does not exist.', $path));
        }
        $entries = require $path;
        if (!is_array($entries)) {
            throw new \UnexpectedValueException('Schedule manifest must return an array.');
        }
        $schedule = new Schedule();
        foreach ($entries as $entry) {
            if (!is_array($entry)) {
                throw new \UnexpectedValueException('Invalid schedule manifest entry.');
            }
            $scheduled = $schedule->command((string) ($entry['command'] ?? ''))->cron((string) ($entry['cron'] ?? ''))->timezone((string) ($entry['timezone'] ?? 'UTC'));
            if (($entry['without_overlap'] ?? false) === true) {
                $scheduled->withoutOverlap();
            }
            if (($entry['on_one_server'] ?? false) === true) {
                $scheduled->onOneServer();
            }
        }

        return $schedule;
    }
}
