<?php

declare(strict_types=1);

namespace Infocyph\Console\Scheduling;

use Infocyph\Console\Support\ManifestValue;

/** @internal */
final class ScheduleManifest
{
    public static function load(string $path): Schedule
    {
        if (!is_file($path)) {
            throw new \InvalidArgumentException(sprintf('Schedule manifest "%s" does not exist.', $path));
        }
        $entries = ManifestValue::mapList(require $path, 'schedule');
        $schedule = new Schedule();
        foreach ($entries as $index => $entry) {
            $scheduled = $schedule
                ->command(ManifestValue::string($entry['command'] ?? null, 'schedule.' . $index . '.command'))
                ->cron(ManifestValue::string($entry['cron'] ?? null, 'schedule.' . $index . '.cron'))
                ->timezone(ManifestValue::string(
                    $entry['timezone'] ?? null,
                    'schedule.' . $index . '.timezone',
                    'UTC',
                ));
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
