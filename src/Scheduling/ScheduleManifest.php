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
                ->arguments(ManifestValue::stringList(
                    $entry['arguments'] ?? [],
                    'schedule.' . $index . '.arguments',
                ))
                ->cron(ManifestValue::string($entry['cron'] ?? null, 'schedule.' . $index . '.cron'))
                ->timezone(ManifestValue::string(
                    $entry['timezone'] ?? null,
                    'schedule.' . $index . '.timezone',
                    'UTC',
                ));
            if (($entry['without_overlap'] ?? false) === true) {
                $scheduled->withoutOverlap(
                    leaseSeconds: self::number(
                        $entry['overlap_lease_seconds'] ?? 300.0,
                        'schedule.' . $index . '.overlap_lease_seconds',
                    ),
                    waitSeconds: self::number(
                        $entry['overlap_wait_seconds'] ?? 0.0,
                        'schedule.' . $index . '.overlap_wait_seconds',
                    ),
                );
            }
            if (($entry['on_one_server'] ?? false) === true) {
                $scheduled->onOneServer(
                    leaseSeconds: self::number(
                        $entry['overlap_lease_seconds'] ?? 300.0,
                        'schedule.' . $index . '.overlap_lease_seconds',
                    ),
                    waitSeconds: self::number(
                        $entry['overlap_wait_seconds'] ?? 0.0,
                        'schedule.' . $index . '.overlap_wait_seconds',
                    ),
                );
            }
            $timeout = self::nullableNumber($entry['timeout_seconds'] ?? null, 'schedule.' . $index . '.timeout_seconds');
            if ($timeout !== null) {
                $scheduled->timeout(
                    $timeout,
                    self::number(
                        $entry['termination_grace_seconds'] ?? 5.0,
                        'schedule.' . $index . '.termination_grace_seconds',
                    ),
                );
            }
            $idleTimeout = self::nullableNumber(
                $entry['idle_timeout_seconds'] ?? null,
                'schedule.' . $index . '.idle_timeout_seconds',
            );
            if ($idleTimeout !== null) {
                $scheduled->idleTimeout($idleTimeout);
            }
            $memory = $entry['memory_limit_megabytes'] ?? null;
            if ($memory !== null) {
                if (!is_int($memory)) {
                    throw new \UnexpectedValueException(sprintf(
                        'Manifest field "schedule.%d.memory_limit_megabytes" must be an integer.',
                        $index,
                    ));
                }
                $scheduled->memoryLimit($memory);
            }
        }

        return $schedule;
    }

    private static function nullableNumber(mixed $value, string $field): ?float
    {
        return $value === null ? null : self::number($value, $field);
    }

    private static function number(mixed $value, string $field): float
    {
        if (!is_int($value) && !is_float($value)) {
            throw new \UnexpectedValueException(sprintf('Manifest field "%s" must be numeric.', $field));
        }

        return (float) $value;
    }
}
